# EDPay WHMCS Payment Gateway Module

[![WHMCS](https://img.shields.io/badge/WHMCS-8.x-green.svg)](https://whmcs.com)
[![Status](https://img.shields.io/badge/Status-Coming%20Soon-amber.svg)](https://edpay.co.mz)

Módulo oficial de pagamentos da **EDPay** para **WHMCS** (Web Host Manager Complete Solution), permitindo a provedores de alojamento web, ISPs e telecomunicações cobrarem faturas através dos canais de pagamento de Moçambique.

---

## Funcionalidades

- Compatível com o framework de Gateways de Pagamento de Terceiros do WHMCS.
- Liquidação automática de facturas via IPN / Webhook com verificação de assinatura HMAC SHA-256 (`modules/gateways/callback/edpay.php`).
- Suporte a M-Pesa, e-Mola, Cartões Visa/Mastercard (3DS), Apple Pay, Google Pay e Referências Bancárias.
- Condições Comerciais: Tarifa plana de 6% em MZN, liquidações bancárias de 2 a 7 dias úteis.
