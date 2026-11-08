# Módulo de Pagamentos EDPay para WHMCS

[![Licença: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Versão WHMCS](https://img.shields.io/badge/WHMCS-7.x%20|%208.x-orange.svg)](https://whmcs.com)
[![Multi-Gateway](https://img.shields.io/badge/EDPay-Multi--Gateway-0051FA.svg)](https://edpay.co.mz)

Integre a plataforma **EDPay** ao seu sistema de faturação e hosting **WHMCS**, permitindo que os seus clientes em Moçambique liquidem faturas via **Carteiras Móveis (M-Pesa, e-Mola)**, **Cartões Visa / Mastercard**, e **Referências Bancárias** em Meticais (MZN) com confirmação instantânea via webhook assinado (HMAC-SHA256).

---

## 🚀 Funcionalidades

- **Checkout Multi-Gateway**: O cliente escolhe livremente o canal de pagamento (M-Pesa, e-Mola, Cartão de Débito/Crédito ou Referência Bancária).
- **Liquidação Automática de Faturas**: Webhook em tempo real dá baixa na fatura no WHMCS logo que o pagamento é autorizado.
- **Assinatura HMAC-SHA256**: Validação rigorosa do header `X-EDPay-Signature` para prevenir requisições forjadas.
- **Suporte a Sandbox & Live**: Alterne entre chaves de teste (`pk_test_...`) e produção (`pk_live_...`) com um clique.
- **Tarifa Transparente**: Taxa plana de 6% em MZN com reconciliação contábil automática.

---

## 📦 Estrutura de Arquivos

```
edpay-whmcs-gateway/
├── modules/
│   └── gateways/
│       ├── edpay.php              # Definição e lógica do gateway WHMCS
│       └── callback/
│           └── edpay.php          # Listener de webhooks com verificação HMAC
├── README.md
└── LICENSE
```

---

## 🛠️ Instalação Passo a Passo

### 1. Copiar Arquivos para o Servidor WHMCS
Copie a pasta `modules/` deste repositório para o diretório raiz da sua instalação WHMCS:

```bash
cp -r modules/ /var/www/whmcs/modules/
```

### 2. Ativar o Gateway no Painel WHMCS
1. Inicie sessão no painel administrativo do WHMCS.
2. Navegue até **Setup (Configurações) > Payments (Pagamentos) > Payment Gateways**.
3. Na aba **All Payment Gateways**, localize **EDPay Multi-Gateway** e clique em **Activate**.

### 3. Configurar Parâmetros de API
Preencha os campos com as credenciais obtidas no painel da EDPay (**Desenvolvedores > Chaves de API**):
- **Display Name**: `EDPay Multi-Gateway (M-Pesa, e-Mola, Cartões)`
- **Chave Pública**: `pk_live_...` (ou `pk_test_...` para testes)
- **Chave Privada**: `sk_live_...` (ou `sk_test_...`)
- **Segredo do Webhook**: `whsec_...`
- **Modo Sandbox de Testes**: Marque caso esteja usando chaves de teste.

Clique em **Save Changes**.

### 4. Configurar Webhook no Painel EDPay
1. Acesse o painel EDPay em **Desenvolvedores > Webhooks**.
2. Clique em **Criar Webhook** e insira a URL de callback do seu WHMCS:
   ```
   https://seu-whmcs.co.mz/modules/gateways/callback/edpay.php
   ```
3. Selecione os eventos `payment.succeeded` e `payment.failed`.
4. Copie o segredo `whsec_...` gerado e guarde no WHMCS.

---

## 🔒 Segurança e Melhores Práticas

- A chave privada (`sk_live_...`) e o segredo do webhook (`whsec_...`) nunca devem ser expostos publicamente.
- O script `callback/edpay.php` valida a assinatura criptográfica antes de chamar `addInvoicePayment()`.
- O WHMCS registra todas as notificações em **Billing > Gateway Log**.

---

## 📄 Licença

Distribuído sob licença MIT. Consulte `LICENSE` para mais detalhes.
