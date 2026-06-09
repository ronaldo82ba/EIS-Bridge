# EIS Bridge Vendor API

Laravel application providing the EIS Bridge Vendor API — POS-vendor-first BIR EIS middleware for the Philippines.

## What it does

- Accepts **Standard Sale Object** JSON via `POST /v1/transactions`
- Returns immediate async acceptance (`processing_status: queued`)
- Maps to BIR EIS JSON, signs with merchant certificates, and transmits via background queue workers
- Exposes status polling, batch submit, webhooks, and admin onboarding APIs

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Default sandbox API key: `VENDOR_API_KEY_123` (see `SANDBOX_API_KEY` in `.env`).

## Documentation

Full integration docs live in the repository root:

- [POS Developer Integration Guide](../docs/pos-developer-integration-guide.md)
- [Vendor API Reference](../docs/vendor-api.md)
- [Certification Playbook](../docs/certification-playbook.md)
- [Partner Program](../docs/partner-program.md)
- [Developer Portal](../portal/index.html)

## Compliance

EIS Bridge is not affiliated with or accredited by the BIR. BIR certifies taxpayer systems, not software providers. Each merchant taxpayer remains responsible for EIS CERT and Permit to Transmit (PTT).

---

© 2026 EIS Bridge
