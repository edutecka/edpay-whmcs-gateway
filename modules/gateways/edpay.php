<?php
/**
 * EDPay Payment Gateway Module for WHMCS
 *
 * Process multi-gateway payments in Mozambique (Meticais - MZN)
 * Supports Mobile Money (M-Pesa, e-Mola), Cards, and Bank References.
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
        'DisplayName' => 'EDPay Payment Gateway (MZN Multi-Gateway)',
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
            'Value' => 'EDPay Multi-Gateway (M-Pesa, e-Mola, Cartões)',
        ],
        'publicKey' => [
            'FriendlyName' => 'Chave Pública (Public Key)',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'pk_live_... ou pk_test_... obtida no Painel EDPay em Desenvolvedores > Chaves API.',
        ],
        'privateKey' => [
            'FriendlyName' => 'Chave Privada (Private Key)',
            'Type' => 'password',
            'Size' => '64',
            'Default' => '',
            'Description' => 'sk_live_... ou sk_test_... (mantida em segurança absoluta no servidor).',
        ],
        'webhookSecret' => [
            'FriendlyName' => 'Segredo do Webhook (Webhook Secret)',
            'Type' => 'password',
            'Size' => '64',
            'Default' => '',
            'Description' => 'whsec_... configurado no Painel EDPay para verificação da assinatura HMAC SHA-256.',
        ],
        'testMode' => [
            'FriendlyName' => 'Modo Sandbox de Testes',
            'Type' => 'yesno',
            'Description' => 'Marque para usar o ambiente Sandbox de simulação da EDPay.',
        ],
        'apiBaseUrl' => [
            'FriendlyName' => 'URL Base da API (Opcional)',
            'Type' => 'text',
            'Size' => '80',
            'Default' => 'https://api-pay.edutecka.com',
            'Description' => 'URL da infraestrutura da API EDPay (padrão: https://api-pay.edutecka.com).',
        ],
    ];
}

/**
 * Payment link generation
 */
function edpay_link(array $params): string
{
    $invoiceId = (string) $params['invoiceid'];
    $description = (string) ($params['description'] ?? "Fatura #{$invoiceId}");
    $amount = (float) $params['amount'];
    $currency = (string) ($params['currency'] ?? 'MZN');

    $publicKey = trim((string) ($params['publicKey'] ?? ''));
    $privateKey = trim((string) ($params['privateKey'] ?? ''));
    $baseUrl = rtrim((string) ($params['apiBaseUrl'] ?? 'https://api-pay.edutecka.com'), '/');
    $systemUrl = rtrim((string) ($params['systemurl'] ?? ''), '/');

    // Customer details
    $firstname = (string) ($params['clientdetails']['firstname'] ?? '');
    $lastname = (string) ($params['clientdetails']['lastname'] ?? '');
    $email = (string) ($params['clientdetails']['email'] ?? '');
    $phonenumber = (string) ($params['clientdetails']['phonenumber'] ?? '');

    $returnUrl = "{$systemUrl}/viewinvoice.php?id={$invoiceId}";
    $cancelUrl = "{$systemUrl}/clientarea.php?action=invoices";

    // Build Hosted Checkout creation payload
    $payload = [
        'title' => "Fatura WHMCS #{$invoiceId}",
        'description' => $description,
        'amount' => (int) round($amount * 100), // In centavos
        'currency' => $currency,
        'metadata' => [
            'whmcs_invoice_id' => $invoiceId,
            'client_email' => $email,
            'client_name' => trim("{$firstname} {$lastname}"),
        ],
        'success_url' => $returnUrl,
        'cancel_url' => $cancelUrl,
    ];

    // Request Checkout link from EDPay Server-to-Server
    $ch = curl_init("{$baseUrl}/v1/core/payment-links");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "X-EDPay-Public-Key: {$publicKey}",
            "X-EDPay-Private-Key: {$privateKey}",
            "Idempotency-Key: whmcs-inv-{$invoiceId}-" . time(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode < 200 || $httpCode >= 300) {
        return '<div class="alert alert-danger" style="margin: 10px 0; padding: 12px; border-radius: 6px;">' .
            '<strong>Erro ao conectar à EDPay:</strong> Não foi possível gerar a sessão de pagamento no momento. ' .
            'Por favor contacte o suporte. (HTTP ' . htmlspecialchars((string) $httpCode) . ')' .
            '</div>';
    }

    $data = json_decode((string) $rawResponse, true);
    $checkoutUrl = $data['data']['hosted_url'] ?? $data['data']['checkout_url'] ?? null;

    if (!$checkoutUrl) {
        return '<div class="alert alert-warning" style="margin: 10px 0; padding: 12px; border-radius: 6px;">' .
            'Sessão criada mas a URL de checkout não foi retornada. Verifique as configurações de chave no WHMCS.' .
            '</div>';
    }

    return '<form method="GET" action="' . htmlspecialchars($checkoutUrl) . '">' .
        '<button type="submit" class="btn btn-primary btn-block btn-lg" style="background-color: #0051FA; border-color: #0051FA; font-weight: 600; padding: 12px 24px; border-radius: 8px; color: #fff;">' .
        'Pagar com EDPay (M-Pesa, e-Mola, Cartões)' .
        '</button>' .
        '</form>';
}
