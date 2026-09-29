# EIS Bridge — Commercial Pricing (Locked)

> **Status:** Sales / product truth for WebShoppe SaaS licensing of EIS Bridge access.  
> **Not** BIR sales invoices (`invoices` / CodeBooks SI). Billing catalog rows live in `license_plans` (see [Billing & Licensing](billing-licensing.md)).

## Disclaimer — BIR Ready, not BIR Accredited

EIS Bridge is **BIR Ready** (technical readiness for taxpayer EIS / transmission workflows). It is **not BIR Accredited**. WebShoppe does not claim BIR accreditation, guaranteed compliance, or replacement of taxpayer CERT / PTT / CPA obligations. Merchants remain responsible for their own BIR registration and certification paths.

---

## Vendor / Distributor

| Item | Amount | Notes |
|------|--------|--------|
| Vendor setup (`vendor_distributor_setup_350k`) | **₱350,000** | One-time; **includes Distributorship fee** |

**Channel:** Vendor pays the ₱350,000 setup (distributorship included) → sells EIS Bridge to merchants. Merchant-facing prices remain the store / Lite / postpaid plans below (₱35k per store activation, postpaid monthly tiers, Lite prepaid).

---

## Prepaid — EIS Bridge Lite

| Item | Amount | Notes |
|------|--------|--------|
| Setup (`lite_setup`) | **₱17,000** | One-time |
| Monthly | **None** | Prepaid only |
| Usage | **₱1 per e-invoice upload** | Manual create/upload (“mano-mano”) |
| Wallet (`lite_prepaid_wallet`) | Prepaid balance | Example: **₱3,000** ≈ **3,000** uploads at ₱1; when balance hits zero, recharge before more uploads |

**Flow:** pay setup → load prepaid credits → each upload deducts ₱1 → recharge when exhausted.

### Assumptions (open confirmations — defaults until product says otherwise)

| Topic | Default assumption |
|-------|--------------------|
| First wallet load | **Separate** from the ₱17,000 setup unless a quote explicitly includes credits. The ₱3,000 figure is an **example** wallet load, not a free included package. |
| Unit price | Catalog amount for `lite_prepaid_wallet` is **₱1** per upload (`per_unit`); typical top-ups (e.g. ₱3,000) are purchase quantity × ₱1. |

---

## Postpaid — per merchant / store

| Item | Amount | Notes |
|------|--------|--------|
| Store / merchant activation (`store_activation_35k`) | **₱35,000** | One-time per store/merchant (**assumption** until product locks annual vs one-time) |
| Standard volume (`postpaid_tier_1500`) | **₱1,500 / month** | **≤800** e-invoices **per day** |
| High volume (`postpaid_tier_2500`) | **₱2,500 / month** | Up to **3,000** e-invoices **per day** |

### Assumptions (open confirmations — defaults until product says otherwise)

| Topic | Default assumption |
|-------|--------------------|
| ₱35,000 | **One-time** per store/merchant activation (not annual), unless sales explicitly sells an annual renewal SKU later. |
| Daily caps | Counted on a **calendar day** in **Asia/Manila**. |
| Overage | **Block uploads** until the merchant upgrades tier or (for Lite) recharges prepaid balance. No overage fee by default. |

---

## Catalog slugs (LicensePlanSeeder)

| Slug | Billing model | Unit | Amount (PHP) | Role |
|------|---------------|------|--------------|------|
| `vendor_distributor_setup_350k` | one_time | vendor | 350,000.00 | Vendor setup incl. distributorship |
| `lite_setup` | one_time | merchant | 17,000.00 | Lite one-time setup |
| `lite_prepaid_wallet` | per_unit | merchant | 1.00 | ₱1 / e-invoice upload (prepaid wallet ledger) |
| `store_activation_35k` | one_time | merchant | 35,000.00 | Postpaid store/merchant activation |
| `postpaid_tier_1500` | recurring_monthly | merchant | 1,500.00 | ≤800 e-invoices/day |
| `postpaid_tier_2500` | recurring_monthly | merchant | 2,500.00 | Up to 3,000 e-invoices/day |

Legacy vendor / SaaS plan rows remain in the seeder for existing tests and admin flows; **this document supersedes those amounts for sales quotes**.

---

## CodeBooks Lite + EIS Bridge™ Bundle

Public launch SKU (`codebooks_lite_eis_bridge_bundle_35k`). Deposit-first. No payment gateway on the page.

| Item | Amount | Notes |
|------|--------|--------|
| Regular / list | **₱35,000** | After the launch cap |
| Launch promo | **₱15,000** | **First 100 takers** only |
| Promo end | **100 takers** | Cap, not a calendar end date |

CodeBooks Lite = SI/OR + lite books. EIS Bridge™ = BIR-ready connector (export → map → sign → queue → transmit). The taxpayer completes EIS CERT and PTT.

**Not the same SKU as EIS Bridge Lite prepaid** (`lite_setup` ₱17,000 + ₱1/upload). Full CodeBooks + EIS Bridge remains **₱65,000**. CodeBooks alone remains **₱35,000** (SI/OR without EIS Bridge).

---

## GlobalShoppe Celsura + EIS Bridge™ Bundle — Enterprise Edition

Public launch SKU on the Celsura marketing face (`celsura_eis_bridge_bundle_enterprise_150k`). Deposit-first. No payment gateway on the page.

| Item | Amount | Notes |
|------|--------|--------|
| Regular / list | **₱150,000** | Enterprise Edition after the launch cap |
| Launch promo | **₱75,000** | **First 100 takers** only |
| Launch date | **15 September 2026** | Promo starts this date |
| Promo end | **100 takers** | Cap, not a calendar end date |

Celsura = books and principal SI/OR. EIS Bridge™ = BIR-ready connector (export → map → sign → queue → transmit). The taxpayer completes EIS CERT and PTT. Celsura does not transmit live to BIR.

---

## Operational enforcement (implemented)

Wallet ledger + Manila daily metering are live in the Laravel API. No payment gateway — admins assign licenses and recharge wallets; `billing_invoices` cover monthly postpaid.

### Prepaid wallet ledger

- Tables: `merchants.prepaid_wallet_balance`, `prepaid_wallet_ledgers` (credit/debit, reason, optional `billing_invoice_id` / `invoice_id`).
- Merchants with active Lite plans (`lite_setup` and/or `lite_prepaid_wallet`) debit **₱1** on each accepted e-invoice upload.
- **Block** with `insufficient_prepaid_balance` when balance is below ₱1.
- Admin recharge: `POST /admin/merchants/{id}/wallet/recharge` with `{ "amount": 3000 }` (super_admin / vendor_admin). Show: `GET /admin/merchants/{id}/wallet`.

### Daily volume metering (postpaid)

- Counter table: `merchant_daily_volumes` (`merchant_id` + Asia/Manila `usage_date`).
- Active `postpaid_tier_1500` → cap **800**/day; `postpaid_tier_2500` → **3000**/day (higher tier wins if both present).
- **Block** with `daily_volume_cap_exceeded` when cap reached; resets next Manila calendar day.
- Auto-tier switching between ₱1,500 and ₱2,500 is still out of scope.

### License / ingest hook

`CommercialBillingEnforcer` runs from `TransactionProcessor` after identity validation and basic license checks. Legacy-only licenses keep prior permissive metering (no wallet/cap). Suspended/expired licenses still blocked by `LicenseEnforcement`.

---

*Document version: 1.4 — 2026-09-10 · CodeBooks Lite (₱35k / ₱15k × 100) + GlobalShoppe Celsura Enterprise (₱150k / ₱75k × 100 from 15 Sep 2026)*
