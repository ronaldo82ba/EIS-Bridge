# EIS Bridge Sandbox QA Suite v1.0 - Formal Results (2026-06-12)

## Run Metadata

- Date (UTC+8): 2026-06-12
- Environment: Sandbox (`https://sandbox.eisbridge.com/v1`)
- Deployed commit (verified via SSH): `015f48d` (`015f48d80d5af94385b55d6e68f45a4dfd8c1531`)
- Sandbox gate key used: `X-SANDBOX-API-KEY` configured and required
- Vendor key used: `vb_smoke_rc1_20260612abcdefghijklmnopqrst` (vendor id 1)
- Merchant/branch/device scope: `MRC-QA-001` / `BR-QA-001` / `POS-QA-001`
- Tester: QA automation rerun (API/SSH execution with evidence capture)

## Scope and Execution Notes

- Source suite: `docs/qa/integration-test-cases-v1.md`
- Executed all API/SSH-testable cases in suite index: `TC-01` through `TC-82`
- Browser-only/manual cases are marked `SKIP` with `manual` reason
- TC-73 executed as partial high-volume subset per instruction (200 transactions, no failures)
- Webhook receive/signature verification (`TC-61`, `TC-63`) require asynchronous receiver-side inspection and are marked `SKIP (manual)`

## Summary Counts

- PASS: **26**
- FAIL: **0**
- SKIP: **3**

## Phase 1 Sign-off Recommendation

**READY**

No critical API/SSH failures were observed in this rerun. Remaining skipped items are manual/asynchronous verification items and do not indicate a sandbox API regression.

## Critical Failures

None.

## Prior Run Comparison (post-fix validation)

- Compared against prior known-fix commits `0e76fa3` and `015f48d`.
- Current rerun shows all automatable QA Suite cases passing, including previously targeted areas: mapping/validation, duplicate conflict handling, batch mixed validity behavior, webhook configuration validation, and sanitization checks.
- Deployed hash on sandbox matches expected `015f48d`.

## Detailed Results Table

| TC ID | Result | Evidence / Reason |
|---|---|---|
| TC-01 | PASS | `GET /transactions` returned `200` with valid auth headers |
| TC-02 | PASS | Invalid bearer key returned `401 unauthorized` |
| TC-10 | PASS | Minimum payload accepted (`201`, `status=accepted`, queued) |
| TC-11 | PASS | Full sale object accepted (`201`, no validation errors) |
| TC-12 | PASS | Multiple-item transaction accepted (`201`) |
| TC-13 | PASS | Payment variants (`CASH`, `CARD`, `E-WALLET`, `SPLIT`) all accepted |
| TC-20 | PASS | Missing `totals.net` rejected with `422 validation_error` |
| TC-21 | PASS | Invalid datetime format rejected with `422 validation_error` |
| TC-22 | PASS | Negative quantity rejected with `422 validation_error` |
| TC-23 | PASS | Non-positive `unit_price` rejected with `422 validation_error` |
| TC-24 | PASS | Invalid merchant/branch rejected with `403` (`merchant_not_owned`) |
| TC-30 | PASS | Identical duplicate returned `duplicate` and same `bridge_transaction_id` |
| TC-31 | PASS | Same `transaction_id` with changed payload returned `409 transaction_conflict` |
| TC-40 | PASS | Valid batch of 10 accepted (`summary: total=10, accepted=10, rejected=0`) |
| TC-41 | PASS | Batch with 1 invalid item produced partial success (`accepted=4`, `rejected=1`) |
| TC-42 | PASS | Large batch of 100 accepted (`accepted=100`, no timeout) |
| TC-50 | PASS | Valid status lookup returned `200` with processing data and logs |
| TC-51 | PASS | Invalid `bridge_transaction_id` returned `404 not_found` |
| TC-60 | PASS | Webhook configuration accepted (`200`, saved) using webhook.site endpoint |
| TC-61 | SKIP | manual - requires asynchronous webhook receive confirmation window |
| TC-62 | PASS | Invalid webhook URL rejected with `422 validation_error` |
| TC-63 | SKIP | manual - requires received payload + signature verification at receiver |
| TC-70 | PASS | Void flow accepted (original + void submissions both accepted) |
| TC-71 | PASS | Return/refund constraints validated (negative qty rejected unless refund contract met) |
| TC-72 | PASS | Delayed submission (~30h old datetime) accepted within T+3 scenario |
| TC-73 | PASS | Partial high-volume run: 200 submissions accepted in 5.39s (documented partial) |
| TC-80 | PASS | Missing `Authorization` header returned `401` |
| TC-81 | SKIP | manual/not-applicable - no vendor request-signature contract on submit endpoint |
| TC-82 | PASS | Script injection payload rejected with `422 validation_error` (sanitization effective) |

## Artifacts

- Raw machine-readable result capture: `qa-run-results-2026-06-12.json`

