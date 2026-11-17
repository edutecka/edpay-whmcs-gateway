<?php
/**
 * EDPay Payment Gateway Module for WHMCS
 *
 * Process payments through the methods available from EDPay.
 *
 * @copyright 2026 EDPay
 * @license   MIT
 * @link      https://edpay.co.mz
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module metadata
 */
function edpay_MetaData(): array
{
    return [
        'DisplayName' => 'EDPay Payment Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * Define gateway configuration options
 */
function edpay_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'EDPay Payment Gateway',
        ],
        'publicKey' => [
            'FriendlyName' => 'Public Key',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Required with its matching private key: use pk_test_... with sk_test_..., or pk_live_... with sk_live_....',
        ],
        'privateKey' => [
            'FriendlyName' => 'Private Key',
            'Type' => 'password',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Required with its matching public key. Keep it on the server.',
        ],
        'webhookSecret' => [
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Enter the webhook signing secret (whsec_...) configured in EDPay for HMAC-SHA256 verification.',
        ],
        'webhookToleranceSeconds' => [
            'FriendlyName' => 'Webhook Timestamp Tolerance (seconds)',
            'Type' => 'text',
            'Size' => '6',
            'Default' => '300',
            'Description' => 'Maximum accepted timestamp age or clock skew for Payment-Signature (default: 300 seconds).',
        ],
        'testMode' => [
            'FriendlyName' => 'Sandbox Mode (Inactive)',
            'Type' => 'yesno',
            'Description' => 'This setting is currently inactive and does not select the API environment. Use API Base URL to choose the endpoint.',
        ],
        'apiBaseUrl' => [
            'FriendlyName' => 'API Base URL (Optional)',
            'Type' => 'text',
            'Size' => '80',
            'Default' => 'https://api-pay.edutecka.com',
            'Description' => 'Base URL for the EDPay API (default: https://api-pay.edutecka.com).',
        ],
    ];
}

/**
 * Create payment and hosted checkout
 */
function edpay_link(array $params): string
{
    $publicKey = trim((string) ($params['publicKey'] ?? ''));
    $privateKey = trim((string) ($params['privateKey'] ?? ''));
    $publicMode = preg_match('/^pk_(test|live)_[a-fA-F0-9]{24}$/D', $publicKey, $publicMatch) ? $publicMatch[1] : null;
    $privateMode = preg_match('/^sk_(test|live)_[a-fA-F0-9]{64}$/D', $privateKey, $privateMatch) ? $privateMatch[1] : null;
    if ($publicMode === null || $privateMode === null || $publicMode !== $privateMode) {
        return '<div class="alert alert-danger" style="margin: 10px 0; padding: 12px; border-radius: 6px;">Configure a valid EDPay public key and private key from the same environment.</div>';
    }

    $schema = \WHMCS\Database\Capsule::schema();
    if (!$schema->hasTable('mod_edpay_order_payments')) {
        $schema->create('mod_edpay_order_payments', function ($table) {
            $table->string('reference', 100)->primary();
            $table->unsignedInteger('invoice_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('payment_id', 100)->nullable()->unique();
            $table->timestamps();
        });
    }
    $invoiceId = (string) $params['invoiceid'];
    $description = (string) ($params['description'] ?? "Invoice #{$invoiceId}");
    $amount = (float) $params['amount'];
    $currency = (string) ($params['currency'] ?? 'MZN');

    $baseUrl = rtrim((string) ($params['apiBaseUrl'] ?? 'https://api-pay.edutecka.com'), '/');
    $systemUrl = rtrim((string) ($params['systemurl'] ?? ''), '/');

    $headers = [
        'Content-Type: application/json',
        "X-EDPay-Public-Key: {$publicKey}",
        "X-EDPay-Private-Key: {$privateKey}",
    ];
    $request = function (string $url, array $body, string $key) use ($headers): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge($headers, ["Idempotency-Key: {$key}"]),
            CURLOPT_POSTFIELDS => ($body === [] ? '{}' : json_encode($body)),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [$code, json_decode((string) $response, true), $error];
    };
    $prefix = 'whmcs-invoice-' . $invoiceId;
    [$httpCode, $payment, $curlError] = $request("{$baseUrl}/v1/payments", [
        'amount_minor' => (int) round($amount * 100),
        'currency' => $currency,
        'reference' => $invoiceId,
        'description' => $description,
    ], "{$prefix}-payment-create");
    $paymentId = $payment['data']['id'] ?? null;
    if (!$curlError && $httpCode >= 200 && $httpCode < 300 && is_string($paymentId) && $paymentId !== '') {
        \WHMCS\Database\Capsule::table('mod_edpay_order_payments')->updateOrInsert(
            ['reference' => $invoiceId],
            ['payment_id' => $paymentId, 'amount_minor' => (int) round($amount * 100), 'currency' => $currency, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        );
        [$sessionCode, $session, $sessionError] = $request("{$baseUrl}/v1/payments/" . rawurlencode($paymentId) . "/public-checkout", [], "{$prefix}-public-checkout");
        $token = $session['data']['token'] ?? $session['token'] ?? null;
        if (!$sessionError && $sessionCode >= 200 && $sessionCode < 300 && is_string($token) && $token !== '') {
            $checkoutUrl = 'https://pay.edutecka.com/pay?' . http_build_query([
                'token' => $token, 'pk' => $publicKey,
            ], '', '&', PHP_QUERY_RFC3986);
        }
    }

    if (empty($checkoutUrl)) {
        return '<div class="alert alert-danger" style="margin: 10px 0; padding: 12px; border-radius: 6px;">' .
            '<strong>Unable to connect to EDPay:</strong> The payment session could not be created. ' .
            'Please contact support. (HTTP ' . htmlspecialchars((string) $httpCode) . ')' .
            '</div>';
    }

    return '<form method="GET" action="' . htmlspecialchars($checkoutUrl) . '">' .
        '<button type="submit" class="btn btn-primary btn-block btn-lg" style="background-color: #0051FA; border-color: #0051FA; font-weight: 600; padding: 12px 24px; border-radius: 8px; color: #fff;">' .
        'Pay with EDPay' .
        '</button>' .
        '</form>';
}
