<?php
/**
 * EDPay Webhook Callback Script for WHMCS
 *
 * Verifies HMAC-SHA256 signature from X-EDPay-Signature and reconciles invoices.
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
$signatureHeader = $_SERVER['HTTP_X_EDPAY_SIGNATURE'] ?? '';
$webhookSecret = (string) ($gatewayParams['webhookSecret'] ?? '');

if (empty($rawPayload)) {
    http_response_code(400);
    die(json_encode(['error' => 'Empty request body']));
}

// Verify HMAC-SHA256 signature if secret is configured
if (!empty($webhookSecret)) {
    $expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);
    if (!hash_equals($expectedSignature, $signatureHeader)) {
        logTransaction($gatewayParams['name'], [
            'error' => 'Invalid HMAC Signature',
            'received' => $signatureHeader,
            'body' => $rawPayload,
        ], 'Unsuccessful');
        http_response_code(401);
        die(json_encode(['error' => 'Invalid signature']));
    }
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
$amountCentavos = $data['amount'] ?? 0;
$amount = $amountCentavos / 100;
$feeCentavos = $data['fee_amount'] ?? 0;
$fee = $feeCentavos / 100;
$invoiceId = $data['metadata']['whmcs_invoice_id'] ?? null;

// Only process payment succeeded events
if ($eventType === 'payment.succeeded' || $status === 'succeeded') {
    if (!$invoiceId) {
        // Fallback: search by reference
        $reference = $data['reference'] ?? '';
        if (preg_match('/INV-(\d+)/', $reference, $matches)) {
            $invoiceId = $matches[1];
        }
    }

    if (!$invoiceId) {
        logTransaction($gatewayParams['name'], $event, 'Missing Invoice ID in metadata');
        http_response_code(200);
        die(json_encode(['status' => 'ignored_no_invoice']));
    }

    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($paymentId);

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
