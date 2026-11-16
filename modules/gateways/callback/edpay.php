<?php
/**
 * EDPay Webhook Callback Script for WHMCS
 *
 * Verifies the timestamped Payment-Signature header and reconciles invoices.
 */

// Require WHMCS init
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = 'edpay';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMENT_SIGNATURE'] ?? '';
$webhookSecret = trim((string) ($gatewayParams['webhookSecret'] ?? ''));

if (empty($rawPayload)) {
    http_response_code(400);
    die(json_encode(['error' => 'Empty request body']));
}

$toleranceValue = filter_var($gatewayParams['webhookToleranceSeconds'] ?? 300, FILTER_VALIDATE_INT);
$toleranceSeconds = $toleranceValue !== false && $toleranceValue >= 0 ? $toleranceValue : 300;
$signatureFields = [];
foreach (explode(',', $signatureHeader) as $field) {
    $parts = explode('=', trim($field), 2);
    if (count($parts) === 2 && in_array($parts[0], ['t', 'v1'], true)) {
        if (array_key_exists($parts[0], $signatureFields)) {
            $signatureFields[$parts[0]] = null;
            break;
        }
        $signatureFields[$parts[0]] = trim($parts[1]);
    }
}
$timestamp = $signatureFields['t'] ?? '';
$signature = $signatureFields['v1'] ?? '';
$signedAt = preg_match('/^\d+$/', $timestamp) ? filter_var($timestamp, FILTER_VALIDATE_INT) : false;
$validHeader = $signedAt !== false && preg_match('/^[a-f0-9]{64}$/i', $signature);
$validTimestamp = $validHeader && abs(time() - $signedAt) <= $toleranceSeconds;
$expectedSignature = $validTimestamp ? hash_hmac('sha256', $timestamp . '.' . $rawPayload, $webhookSecret) : '';

if ($webhookSecret === '' || $signatureHeader === '' || !$validTimestamp
    || !hash_equals($expectedSignature, strtolower($signature))) {
    logTransaction($gatewayParams['name'], ['error' => 'Webhook signature verification failed'], 'Unsuccessful');
    http_response_code(401);
    die(json_encode(['error' => 'Invalid signature']));
}

$event = json_decode($rawPayload, true);
if (!is_array($event) || empty($event['type'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON structure']));
}

$eventType = $event['type'];
$data = $event['data'] ?? [];
$paymentId = $data['id'] ?? '';
$status = $data['status'] ?? '';
$amountCentavos = $data['amount_minor'] ?? null;
$amount = is_numeric($amountCentavos) ? ((int) $amountCentavos / 100) : 0;
$feeCentavos = $data['platform_fee_minor'] ?? 0;
$fee = $feeCentavos / 100;
$paymentLinkId = $data['payment_link_id'] ?? '';
$schema = \WHMCS\Database\Capsule::schema();
if (!$schema->hasTable('mod_edpay_order_links')) {
    $schema->create('mod_edpay_order_links', function ($table) {
        $table->string('payment_link_id', 100)->primary();
        $table->unsignedInteger('invoice_id')->index();
        $table->unsignedBigInteger('amount_minor');
        $table->string('currency', 3);
        $table->string('payment_id', 100)->nullable()->unique();
        $table->timestamps();
    });
}
$mapping = $paymentLinkId !== ''
    ? \WHMCS\Database\Capsule::table('mod_edpay_order_links')->where('payment_link_id', $paymentLinkId)->first()
    : null;

// Only process payment succeeded events
if ($eventType === 'payment.succeeded') {
    if (!$mapping || $status !== 'succeeded' || !is_numeric($amountCentavos)
        || (int) $amountCentavos !== (int) $mapping->amount_minor
        || strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($mapping->currency)
        || $paymentId === '') {
        http_response_code(409);
        die(json_encode(['error' => 'Payment link, status, amount, or currency mismatch']));
    }

    if ($mapping->payment_id !== null) {
        http_response_code(409);
        die(json_encode(['error' => 'Payment link already processed']));
    }
    $invoiceId = checkCbInvoiceID($mapping->invoice_id, $gatewayParams['name']);
    $invoice = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    $invoiceCurrency = $invoice
        ? \WHMCS\Database\Capsule::table('tblcurrencies')->where('id', $invoice->currency)->value('code')
        : null;
    if (!$invoice || !in_array(strtolower((string) $invoice->status), ['unpaid', 'payment pending'], true)
        || (int) round((float) $invoice->total * 100) !== (int) $amountCentavos
        || strtoupper((string) $invoiceCurrency) !== strtoupper($mapping->currency)) {
        http_response_code(409);
        die(json_encode(['error' => 'Mapped invoice amount or status mismatch']));
    }
    checkCbTransID($paymentId);
    $claimed = \WHMCS\Database\Capsule::table('mod_edpay_order_links')
        ->where('payment_link_id', $paymentLinkId)->whereNull('payment_id')
        ->update(['payment_id' => $paymentId, 'updated_at' => date('Y-m-d H:i:s')]);
    if ($claimed !== 1) {
        http_response_code(409);
        die(json_encode(['error' => 'Payment link already processed']));
    }

    // Apply payment to WHMCS invoice
    addInvoicePayment(
        $invoiceId,
        $paymentId,
        $amount,
        $fee,
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], $event, 'Successful');
    http_response_code(200);
    echo json_encode(['status' => 'success', 'invoice_id' => $invoiceId]);
    exit;
}

// Acknowledge other events (e.g. pending, failed)
logTransaction($gatewayParams['name'], $event, 'Logged (' . $eventType . ')');
http_response_code(200);
echo json_encode(['status' => 'received']);
