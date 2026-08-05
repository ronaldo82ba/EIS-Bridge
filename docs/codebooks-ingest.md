# CodeBooks → EIS Bridge ingest

Bridge does **not** print RR 7-2024 SI/OR faces. Print/PDF and company profile UI stay in **CodeBooks**. EIS Bridge is map → validate → sign → queue → EIS-ready export only (**BIR Ready**, not BIR Accredited).

## Flow

```
CodeBooks BridgePayloadService export
  → POST /api/admin/codebooks/ingest
  → CodeBooksIngestAdapter
  → Standard Sale Object(s)
  → existing PosJsonValidator → PosToBirMapper → (optional) TransactionProcessor
```

Default is `dry_run=true` (returns Sale Objects + mapped `bir_json` without enqueue). Set `dry_run=false` to commit through the pipeline.

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
