# Roadmap and Architecture Plan - EDPay WHMCS Gateway

## Development Phases

### Phase 1: Architecture and Portal Configuration (Complete)
- [x] Define metadata in `modules/gateways/edpay.php` (`edpay_MetaData()`, `edpay_config()`).
- [x] Support configuration of the public key, private key, webhook secret, and API base URL.
- [x] Remove the inactive Sandbox Mode setting; matching API key prefixes select test or live access on the same API host.

### Phase 2: Payment Parent, Hosted Checkout, and IPN Callback (In Progress)
- [ ] Generate hosted checkout in `edpay_link()`.
- [ ] Add a callback listener in `modules/gateways/callback/edpay.php` with invoice ID, transaction ID, and fee validation.

### Phase 3: WHMCS Marketplace Certification
- [ ] Add automated tests for WHMCS 8.8, 8.9, and 8.10.
- [ ] Publish to the WHMCS Marketplace.
