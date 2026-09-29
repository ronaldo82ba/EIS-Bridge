# CodeBooks → EIS Bridge ingest

Bridge does **not** print RR 7-2024 SI/OR faces. Print/PDF and company profile UI stay in **CodeBooks**. EIS Bridge is map → validate → sign → queue → EIS-ready export only (**BIR Ready**, not BIR Accredited).

## Flow

```
CodeBooks BridgePayloadService export
  → POST ingest (Sanctum admin OR machine token)
  → CodeBooksIngestAdapter
  → Standard Sale Object(s)
  → existing PosJsonValidator → PosToBirMapper → (optional) TransactionProcessor
```

Default is `dry_run=true` (returns Sale Objects + mapped `bir_json` without enqueue). Set `dry_run=false` to commit through the pipeline.

## Auth options

### A) Admin Sanctum (portal / interactive)

`POST /api/admin/codebooks/ingest`  
Headers: `Authorization: Bearer <sanctum_token>`  
Vendor admins are scoped to their `vendor_id`. Super/support must pass `vendor_id`.

### B) Machine token (CodeBooks server→server) — preferred for automation

`POST /api/admin/codebooks/ingest/service`  
Header: `X-Bridge-Ingest-Token: <CODEBOOKS_INGEST_TOKEN>`  
No Sanctum user required.

Tenant safety:

- Set `CODEBOOKS_INGEST_VENDOR_ID` on Bridge (recommended). That vendor is always used.
- Request may also send `vendor_id`; if both are set they **must** match (else 403).
- If config vendor is unset, `vendor_id` in the body is required.

## Bridge env keys

| Key | Purpose |
|-----|---------|
| `CODEBOOKS_INGEST_TOKEN` | Shared secret; compared with timing-safe `hash_equals` against `X-Bridge-Ingest-Token` |
| `CODEBOOKS_INGEST_VENDOR_ID` | Optional but recommended pinned vendor id for machine ingest |

## Example: machine token dry_run

```http
POST /api/admin/codebooks/ingest/service HTTP/1.1
Host: api.eisbridge.example
Content-Type: application/json
X-Bridge-Ingest-Token: your-shared-secret

{
  "vendor_id": 1,
  "dry_run": true,
  "payload": {
    "meta": {
      "product": "CodeBooks by WebShoppe",
      "purpose": "EIS Bridge export payload",
      "disclaimer": "BIR Ready payload only. Not a live BIR transmission. Not BIR Accredited."
    },
    "company": {
      "name": "Demo Merchant Co",
      "trade_name": "Demo Trade",
      "tin": "987-654-321-000",
      "address": "Makati City",
      "vat_registered": true,
      "rdo_code": "050",
      "branch_code": "00001",
      "bir_ack_number": "ACCN-PILOT-9",
      "bir_ack_date": "2026-03-01"
    },
    "documents": [
      {
        "document_type": "SI",
        "number": "SI-2026-000100",
        "date": "2026-08-01",
        "customer": {
          "name": "Buyer Co",
          "tin": "111-222-333-000",
          "address": ""
        },
        "lines": [
          {
            "line_no": 1,
            "sku": "SKU-A",
            "description": "Widget",
            "qty": 1,
            "unit_price": 112.0,
            "vat_rate": 12
          }
        ],
        "totals": {
          "gross_amount": 112.0,
          "net_amount": 100.0,
          "vat_amount": 12.0,
          "vatable_sales": 100.0
        }
      }
    ]
  }
}
```

Successful dry_run returns HTTP 200 with `data.sale_objects`, `data.results[].bir_json`, and `data.dry_run=true`.  
Commit (`dry_run=false`) returns HTTP 201 and enqueues through the existing transaction pipeline.

## Required / recommended `company` fields (CAS-lane pilots)

| Field | Notes |
|--------|--------|
| `name`, `tin`, `address` | Seller identity |
| `trade_name` | Optional trade name |
| `vat_registered` | Boolean |
| `rdo_code` | Optional |
| `branch_code` | Maps to Bridge `Branch` (5-digit normalized) |
| `bir_ack_number` | CAS Acknowledgement Certificate Control No. (ACCN) — **not** PTT/PTU |
| `bir_ack_date` | CAS ack date (YYYY-MM-DD) |

`merchant_ptt` remains optional POS/PTU-lane data and is **not** a substitute for CAS ACCN.

## Books-origin Sale Object policy

- Virtual device per branch: `pos_device_id = CODEBOOKS-VIRTUAL`
- `transaction_id` ← SI number; `transaction_datetime` ← issue date at Asia/Manila start-of-day
- Customer keys always present (`name` / `tin` / `address`), empty string if null
- Payment: method `OTHER`, amount = gross (not a POS tender)

These CAS/company fields appear under `bir_json.merchant` for export completeness. Official BIR EIS nesting for ack fields is not confirmed in-repo.

## CodeBooks client env (caller)

| Key | Purpose |
|-----|---------|
| `EIS_BRIDGE_BASE_URL` | Bridge origin (e.g. `https://api.eisbridge.com`) |
| `EIS_BRIDGE_INGEST_TOKEN` | Same value as Bridge `CODEBOOKS_INGEST_TOKEN` |
| `EIS_BRIDGE_VENDOR_ID` | Bridge vendor id for this CodeBooks tenant |
| `EIS_BRIDGE_DRY_RUN` | Default `true` — map/validate only until you opt into commit |
| `EIS_BRIDGE_ENABLED` | Master switch; when false, issue never calls Bridge |
