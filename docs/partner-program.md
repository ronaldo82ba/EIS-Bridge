# EIS Bridge Vendor Partner Program

EIS Bridge is built **system-vendor-first**: one Vendor API, one Standard Sale Object, and async queued acceptance so checkout never blocks on BIR transmission. POS/ERP/Business System companies integrate once and offer BIR EIS readiness across their merchant install base — without rebuilding their core product for BIR JSON, JWS signing, or transmission retries.

This program is for **POS/ERP/Business System vendors** who want to embed EIS compliance as a native capability. EIS Bridge is an independent product; integration targets include Philippine POS, ERP, and business platforms such as Condor, KaHero, and Mosaic — listed as market segments, not as existing partnerships unless separately announced.

---

## Why partner with EIS Bridge

| Challenge for POS/ERP/Business System vendors | EIS Bridge answer |
|---------------------------|-------------------|
| BIR EIS JSON schema and API churn | Centralized mapping and transmission — one update serves all merchants |
| JWS signing and certificate management | Per-merchant signing handled in the bridge |
| Checkout latency from synchronous BIR calls | Immediate `accepted` / `queued` response; background workers transmit |
| Custom CSV/ETL per client | One Standard Sale Object for every merchant |
| Merchant CERT and PTT complexity | Certification playbook and admin onboarding tools |

Unlike ERP-only middleware (SAP connectors, scheduled ETL for large taxpayers), EIS Bridge is designed for **any POS/ERP/Business System fleet** — multi-branch merchants, franchise operators, and SME install bases.

---

## Product editions

| Edition | Audience | What you get |
|---------|----------|--------------|
| **EIS Bridge Core** | Platform operators | Full middleware — mapping, signing, queue, admin console, Vendor API |
| **EIS Bridge Vendor Edition** | POS/ERP/Business System companies | White-label ready: per-vendor API keys, merchant tenancy, partner economics |
| **EIS Bridge Merchant Edition** | Enterprise retailers | Direct license for large operators managing their own compliance |
| **EIS Bridge SaaS** | Small merchants | Managed cloud compliance via system vendor or direct subscription |

Most POS/ERP/Business System vendors enter through **Vendor Edition**.

---

## Vendor Edition includes

- **Vendor API keys** — unique key per system vendor company, rotatable in the admin console
- **Standard Sale Object** — open integration spec with JSON schema, Postman collection, and QA certification suite
- **Async submission contract** — `POST /transactions` returns immediately; webhooks notify on BIR acknowledgment
- **Multi-merchant tenancy** — onboard merchants, branches, and devices under your vendor account
- **White-label options** — portal and console branding aligned to your product (Vendor Edition); curated exclusive system names: [exclusive-system-names.md](exclusive-system-names.md)
- **Certification support** — sandbox access, QA suite review, production key issuance after sign-off

---

## Integration path

1. **Register** — contact [support@eisbridge.ph](mailto:support@eisbridge.ph) for Vendor Edition terms and sandbox credentials.
2. **Map** — implement POS sale → Standard Sale Object (typically a thin adapter layer).
3. **Submit** — `POST /v1/transactions` with async acceptance; store `bridge_transaction_id`.
4. **Confirm** — poll `GET /transactions/{id}` or configure webhooks for `transaction.eis_acknowledged`.
5. **Certify** — pass QA Suite v1.0; see [certification-playbook.md](certification-playbook.md).
6. **Activate merchants** — each merchant completes EIS CERT and PTT per BIR; enable per-merchant licenses.

Full API reference: [vendor-api.md](vendor-api.md) · Quick start: [pos-developer-integration-guide.md](pos-developer-integration-guide.md)

---

## Partner economics (Vendor Edition)

Pricing is structured for POS vendor channel economics — a core platform license plus per-merchant activation fees as you roll out across your install base.

| Component | Typical range (PHP) | Notes |
|-----------|---------------------|-------|
| **Vendor core license** | ₱180,000 – ₱350,000 | One-time; covers Vendor Edition platform, API access, and onboarding |
| **Per-merchant activation** | ₱25,000 – ₱35,000 | Charged when a merchant goes live with EIS CERT, PTT, and production transmission |
| **Monthly hosting** (optional) | Per agreement | Managed infrastructure for vendors who prefer not to self-host |

Exact pricing depends on merchant volume, white-label scope, and support tier. Contact [support@eisbridge.ph](mailto:support@eisbridge.ph) for a Vendor Edition quote.

Internal billing module reference (platform operators): [billing-licensing.md](billing-licensing.md).

---

## Integration targets (Philippine POS market)

These are **priority integration segments** — POS platforms where a thin Vendor API adapter delivers the highest merchant coverage. No partnership is implied unless publicly announced.

| Segment | Integration approach |
|---------|---------------------|
| **Condor POS** | Map Condor sale/receipt export to Standard Sale Object |
| **KaHero** | Adapter for KaHero transaction API or export format |
| **Mosaic Solutions** | Map multi-branch retail sales to merchant/branch/device codes |
| **Regional / legacy POS** | CSV, database poll, or webhook ingest → Standard Sale Object |

Each adapter is a thin mapping layer (~20% effort per vendor); the BIR transmission core is shared.

---

## Sandbox and production endpoints

| Environment | Base URL | Status |
|-------------|----------|--------|
| Sandbox | `https://sandbox.eisbridge.com/v1` | Provisioned on request during vendor onboarding |
| Production | `https://api.eisbridge.com/v1` | Available after vendor certification |

Local development: run the Laravel API in `api/` with `php artisan serve` and sandbox mode enabled.

---

## Compliance note

EIS Bridge assists with **technical transmission workflows** only. Each merchant taxpayer remains responsible for EIS registration, EIS CERT, PTT, and compliance with applicable Revenue Regulations. BIR does not accredit electronic invoicing software providers — only taxpayer systems are certified.

---

## Get started

Email [support@eisbridge.ph](mailto:support@eisbridge.ph) with:

- Company name and POS product
- Approximate merchant / branch count
- Target go-live timeline (BIR EIS deadlines)
- Preferred edition (Vendor Edition recommended for POS companies)

Developer portal: [portal/index.html](../portal/index.html)

---

© 2026 EIS Bridge
