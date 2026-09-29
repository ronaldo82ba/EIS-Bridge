# EIS Bridge

**Universal, vendor-agnostic BIR EIS compliance platform.**

**EIS Bridge connects any POS, ERP, or merchant to the BIR Electronic Invoicing System — without POS source-code changes.** Merchants still complete EIS CERT and Permit to Transmit (PTT) per BIR; the bridge handles mapping, signing, and transmission.

One Vendor API. One Standard Sale Object. Async queued acceptance.

> EIS Bridge™ — trademark application pending, IPOPHL Class 42, Ref EFPH202600003850268 (Applicant: Ronaldo Mijares)

## Essence

EIS Bridge is the national BIR EIS compliance platform for the Philippines — a universal, vendor-agnostic layer that connects every point-of-sale system, every ERP, and every merchant to the Bureau of Internal Revenue Electronic Invoicing System (BIR EIS). POS vendors integrate once via the Standard Sale Object; EIS Bridge handles BIR JSON mapping, JWS signing, queued transmission, retries, and acknowledgment tracking.

## Positioning

Built **POS-vendor-first**: unlike ERP-centric middleware (scheduled ETL, SAP connectors, enterprise CAS platforms), EIS Bridge gives POS companies a productized Vendor API with immediate `accepted` / `queued` responses so checkout never blocks on BIR transmission.

EIS Bridge is an **independent product** — not affiliated with the BIR or any third-party compliance layer.

## Product Editions

| Edition | License Model | Purpose |
|---------|---------------|---------|
| **EIS Bridge Core** | Platform license | Full middleware — mapping, signing, queue, admin console, Vendor API |
| **EIS Bridge Vendor Edition** | Vendor license (white-label for POS companies) | Certify and deploy EIS readiness across a full POS product portfolio |
| **EIS Bridge Merchant Edition** | Merchant license (enterprise retailers) | Turnkey compliance for enterprise retail operators at scale |
| **EIS Bridge SaaS** | Subscription SaaS (small merchants) | Cloud-hosted compliance as a managed service |

See [Partner Program](docs/partner-program.md) for Vendor Edition economics and onboarding.

## License & Disclosure

EIS Bridge is a licensed platform. Technical stack, architecture, and implementation details are proprietary and not disclosed in public materials.

## Documentation

| Document | Description |
|----------|-------------|
| [POS Developer Integration Guide](docs/pos-developer-integration-guide.md) | Quick-start walkthrough for POS/ERP vendors |
| [EIS Bridge Vendor API](docs/vendor-api.md) | Full API reference — endpoints, webhooks, errors |
| [Standard Sale Object schema](docs/schemas/sale-object.schema.json) | Machine-readable JSON Schema for validation |
| [Certification Playbook](docs/certification-playbook.md) | EIS CERT, PTT, sandbox testing, and go-live steps |
| [Partner Program](docs/partner-program.md) | Vendor Edition, partner economics, integration targets |
| [Postman Collection v1.0](docs/postman/EIS-Bridge-API-v1.postman_collection.json) | Ready-to-import sample requests |
| [QA Integration Test Cases v1.0](docs/qa/integration-test-cases-v1.md) | Structured certification test suite |
| [Forge Deployment Guide](docs/FORGE_DEPLOYMENT.md) | DNS, server requirements, and Forge site setup for eisbridge.com |

## Developer Portal

Static HTML developer documentation lives in the `portal/` folder. Open [`portal/index.html`](portal/index.html) in a browser, or serve the project root and visit `/portal/`.

| Page | Path | Description |
|------|------|-------------|
| Home | `portal/index.html` | Portal landing, CTAs, value proposition |
| Quickstart | `portal/quickstart.html` | Five-step integration walkthrough |
| API Reference | `portal/api.html` | Authentication, endpoints, errors |
| Data Model | `portal/data-model.html` | Standard Sale Object schema and validation |
| Tools | `portal/tools.html` | Postman collection, code samples, JSON schema |
| Testing | `portal/testing.html` | QA test case summary and certification checklist |
| Support | `portal/support.html` | API keys, FAQ, contact |

## API Environments

| Environment | Base URL | Status |
|-------------|----------|--------|
| Sandbox | `https://sandbox.eisbridge.com/v1` | Vendor onboarding (Forge site: sandbox.eisbridge.com) |
| Production | `https://api.eisbridge.com/v1` | Available after vendor certification |

## Local Preview

Open `index.html` directly in your browser, or serve the project folder with any static file server:

```bash
# Example with Python
python -m http.server 8080
```

Then visit `http://localhost:8080` (marketing site) or `http://localhost:8080/portal/` (developer portal).

## API Backend

The EIS Bridge Vendor API is a Laravel application in the `api/` directory. Base URL path: `/v1` (e.g. `http://localhost:8000/v1/transactions`).

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

The `storage:link` command creates the public symlink for uploaded certificate files and other storage-backed assets.

Use the sandbox API key from `.env` (`SANDBOX_API_KEY`, default `VENDOR_API_KEY_123`) in the `Authorization: Bearer` header.

## Compliance Disclaimer

EIS Bridge is independent software and is not affiliated with, endorsed by, or accredited by the Bureau of Internal Revenue (BIR). BIR certifies taxpayer systems, not software providers. Taxpayers remain responsible for EIS registration, EIS CERT, Permit to Transmit (PTT), and compliance with applicable Revenue Regulations. EIS Bridge assists with technical transmission workflows only and does not provide tax, legal, or accounting advice.

---

© 2026 EIS Bridge
