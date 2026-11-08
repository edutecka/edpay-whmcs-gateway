# Roadmap & Plano de Arquitetura - EDPay WHMCS Gateway

## Fases de Desenvolvimento

### Fase 1: Arquitetura & Configuração do Portal (Concluído)
- [x] Definição de metadados em `modules/gateways/edpay.php` (`edpay_MetaData()`, `edpay_config()`).
- [x] Suporte para configuração de Chave Pública, Chave Privada, Segredo do Webhook e Modo Sandbox/Produção.

### Fase 2: Link de Pagamento & Callback de IPN (Em Desenvolvimento)
- [ ] Geração de checkout alojado em `edpay_link()`.
- [ ] Callback listener em `modules/gateways/callback/edpay.php` com validação de invoice ID, trans_id e fee.

### Fase 3: Certificação WHMCS Marketplace
- [ ] Testes automatizados em WHMCS 8.8, 8.9 e 8.10.
- [ ] Publicação no WHMCS Marketplace.
