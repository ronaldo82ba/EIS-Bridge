# EIS Bridge

**Universal, vendor-agnostic BIR EIS compliance platform.**

**EIS Bridge makes ANY POS, ANY ERP, and ANY merchant instantly BIR EIS-compliant — without rewrites, without re-certification, and without touching their source code.**

One system, one API.

## Essence

EIS Bridge is the national BIR EIS compliance platform for the Philippines — a universal, vendor-agnostic layer that connects every point-of-sale system, every ERP, and every merchant to the Bureau of Internal Revenue Electronic Invoicing System (BIR EIS). It removes the burden of bespoke integrations and regulatory uncertainty by providing one trusted bridge between retail operations and government-mandated e-invoicing.

## Positioning

EIS Bridge makes ANY POS, ANY ERP, and ANY merchant instantly BIR EIS-compliant — without rewrites, without re-certification, and without touching their source code.

## Product Editions

| Edition | License Model | Purpose |
|---------|---------------|---------|
| **EIS Bridge** | — | Main platform — universal, vendor-agnostic BIR EIS compliance |
| **EIS Bridge Vendor Edition** | Vendor License (white-label for POS companies) | Certify and deploy EIS readiness across a full POS product portfolio |
| **EIS Bridge Merchant Edition** | Merchant License (enterprise retailers) | Turnkey compliance for enterprise retail operators at scale |
| **EIS Bridge SaaS** | Subscription SaaS (small merchants) | Cloud-hosted compliance as a managed service |

## License & Disclosure

EIS Bridge is a licensed platform. Technical stack, architecture, and implementation details are proprietary and not disclosed in public materials.

## Documentation

Start with the [POS Developer Integration Guide](docs/pos-developer-integration-guide.md) for a quick-start walkthrough. The [EIS Bridge Vendor API](docs/vendor-api.md) defines the full reference for sending standardized sales data to EIS Bridge for BIR EIS mapping, signing, and transmission. A machine-readable [Standard Sale Object schema](docs/schemas/sale-object.schema.json) is included for validation and integration tooling. The [Postman Collection v1.0](docs/postman/EIS-Bridge-API-v1.postman_collection.json) provides ready-to-import sample requests for core API endpoints. The [POS Integration Test Cases (QA Suite v1.0)](docs/qa/integration-test-cases-v1.md) provides structured test cases for go-live certification.

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
| Support | `portal/support.html` | API keys, FAQ, changelog, contact |

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
php artisan serve
```

Use the sandbox API key from `.env` (`SANDBOX_API_KEY`, default `VENDOR_API_KEY_123`) in the `Authorization: Bearer` header.

---

© 2026 EIS Bridge
