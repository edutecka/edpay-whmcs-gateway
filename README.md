# EDPay for WHMCS — Development Preview

**Version:** 1.6.0

This repository contains an EDPay payment gateway integration for WHMCS. Use it only in a sandbox or isolated test installation until you have completed integration, security, and reconciliation testing for your WHMCS version.

The module adds EDPay as an invoice payment method. A client selects the gateway on an invoice, the server creates an EDPay payment request, and the callback records a confirmed payment against the invoice. The callback requires a configured webhook secret and rejects unsigned or unverifiable events.

## Requirements

- WHMCS 7.x or 8.x
- PHP with `curl`, `json`, and `hash`
- An EDPay merchant account with sandbox API keys
- HTTPS for the WHMCS client area and callback endpoint

## Install from source

```bash
git clone https://github.com/edutecka/edpay-whmcs-gateway.git
```

Copy `modules/gateways/edpay.php` and `modules/gateways/callback/edpay.php` to the matching directories in the WHMCS installation. Then activate **EDPay Multi-Gateway** in **System Settings → Payment Gateways**.

## Configure and run a test payment

1. For testing, enter a matching pk_test_... / sk_test_... key pair and webhook secret. For production, use a matching pk_live_... / sk_live_... pair. The key prefixes determine the mode, and the gateway validates that both keys match.
2. Enable the gateway for a test client and invoice.
3. Open the invoice payment page as the test client and select EDPay.
4. Complete the hosted checkout and confirm the invoice is updated only after a verified callback.

Keep the private key and webhook secret out of source control and browser code. The gateway creates one parent payment with `POST /v1/payments`, then starts hosted checkout with `POST /v1/payments/{id}/public-checkout`, using stable invoice-based idempotency keys. It builds the hosted URL from the returned opaque token at `https://pay.edutecka.com/pay?token=...&pk=...`.

## Webhook setup

Register the callback in the EDPay dashboard under **Developers → Webhooks**:

```text
https://billing.example/modules/gateways/callback/edpay.php
```

The gateway stores a durable payment-reference-to-invoice mapping with expected amount and currency. It processes only `payment.succeeded` with `status=succeeded` and matching reference, amount, and currency; duplicate payment IDs are rejected. The callback verifies `Payment-Signature` and rejects invalid signatures or timestamps outside the configurable 300-second default window.

## Operations and security

- Never expose `sk_test_...`, `sk_live_...`, or `whsec_...` in public code, logs, or browser pages.
- Use webhook verification as the source of truth; do not settle an invoice from a browser return alone.
- Review WHMCS gateway logs and reconcile invoice IDs with EDPay payment references.
- The repository is a development preview; validate the callback and API configuration against your target WHMCS version before live use.

## Documentation

- [WHMCS integration guide](https://docs-pay.edutecka.com/plugins/whmcs/)
- [API payments reference](https://docs-pay.edutecka.com/api/payments/)
- [Webhook integration guide](https://docs-pay.edutecka.com/api/webhooks/)
- [SDK installation and integration guide](https://docs-pay.edutecka.com/sdks/)

## Official SDKs

- [JavaScript / TypeScript](https://github.com/edutecka/edpay-javascript-sdk)
- [Python](https://github.com/edutecka/edpay-python-sdk)
- [PHP](https://github.com/edutecka/edpay-php-sdk)
- [Java](https://github.com/edutecka/edpay-java-sdk)
- [.NET](https://github.com/edutecka/edpay-dot-net-sdk)

## Other official plugins and platforms

- [WooCommerce (WordPress)](https://github.com/edutecka/edpay-woocommerce-plugin)
- [Shopify](https://github.com/edutecka/edpay-shopify-app)
- [Magento / Adobe Commerce](https://github.com/edutecka/edpay-magento2-module)
- [PrestaShop](https://github.com/edutecka/edpay-prestashop-module)
- [OpenCart](https://github.com/edutecka/edpay-opencart-extension)
- [WHMCS](https://github.com/edutecka/edpay-whmcs-gateway)

## License

MIT. See [LICENSE](LICENSE).
