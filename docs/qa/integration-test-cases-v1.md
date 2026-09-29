# EIS Bridge — POS Integration Test Cases (QA Suite v1.0)

**Covers functional, validation, error, batch, webhook, and compliance scenarios.**

Use this suite to validate your POS integration against EIS Bridge before go-live. Run tests against the **Sandbox** environment first. Sandbox credentials are provisioned on request during vendor onboarding. For endpoint details, request/response shapes, and error codes, see the [POS Developer Integration Guide](../pos-developer-integration-guide.md) and [EIS Bridge Vendor API](../vendor-api.md). Go-live steps: [Certification Playbook](../certification-playbook.md).

| Environment | Base URL | Status |
|-------------|----------|--------|
| Sandbox | `https://sandbox.eisbridge.com/v1` | Provisioned on request |
| Production | `https://api.eisbridge.com/v1` | After vendor certification |

All requests require:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

---

## Test Case Index

| ID | Title | Section |
|----|-------|---------|
| TC-01 | Valid API Key | Basic Connectivity |
| TC-02 | Invalid API Key | Basic Connectivity |
| TC-10 | Minimum Required Fields | Single Transaction |
| TC-11 | Full Sale Object | Single Transaction |
| TC-12 | Multiple Items | Single Transaction |
| TC-13 | Different Payment Methods | Single Transaction |
| TC-20 | Missing Required Field | Validation & Error Handling |
| TC-21 | Invalid Date Format | Validation & Error Handling |
| TC-22 | Negative Quantity | Validation & Error Handling |
| TC-23 | Zero or Negative Price | Validation & Error Handling |
| TC-24 | Invalid Merchant/Branch Code | Validation & Error Handling |
| TC-30 | Same `transaction_id` sent twice | Duplicate Handling |
| TC-31 | Same `transaction_id` but different data | Duplicate Handling |
| TC-40 | Valid Batch | Batch Submission |
| TC-41 | Batch with 1 invalid transaction | Batch Submission |
| TC-42 | Large Batch (100–500 transactions) | Batch Submission |
| TC-50 | Query valid `bridge_transaction_id` | Status Retrieval |
| TC-51 | Query invalid ID | Status Retrieval |
| TC-60 | Configure Webhook | Webhooks |
| TC-61 | Receive EIS acknowledgment webhook | Webhooks |
| TC-62 | Invalid Webhook URL | Webhooks |
| TC-63 | Signature Verification | Webhooks |
| TC-70 | Void Transaction | Compliance & Edge Cases |
| TC-71 | Return/Refund | Compliance & Edge Cases |
| TC-72 | Offline POS (Delayed Transmission) | Compliance & Edge Cases |
| TC-73 | High Volume Spike | Compliance & Edge Cases |
| TC-80 | Missing Authorization Header | Security |
| TC-81 | Tampered Payload | Security |
| TC-82 | SQL/Script Injection Attempt | Security |

---

## 1. Basic Connectivity Tests

### TC-01: Valid API Key

**Goal:** Ensure vendor can authenticate.

**Steps:**

1. Obtain a valid Vendor API key from EIS Bridge onboarding.
2. Send any authenticated request (e.g. `POST /transactions` with a minimal sale, or `GET /transactions` with filters).
3. Include the API key in the `Authorization` header.

**Expected:**

- HTTP `200` or `201`
- No authentication errors in the response body

---

### TC-02: Invalid API Key

**Steps:**

1. Send any request with an invalid, expired, or malformed API key (e.g. `Authorization: Bearer INVALID_KEY`).

**Expected:**

- HTTP `401`
- Response body:

```json
{
  "error": "unauthorized"
}
```

See [Authentication](../vendor-api.md#2-authentication) in the Vendor API reference.

---

## 2. Single Transaction Submission Tests

Endpoint: `POST /transactions` — see [Submit a Transaction](../pos-developer-integration-guide.md#submit-a-transaction).

### TC-10: Minimum Required Fields

**Goal:** Ensure POS can send the smallest valid sale.

**Steps:**

1. Build a sale object containing only the [required fields](../vendor-api.md#required-fields-minimum).
2. Submit via `POST /transactions`:

```json
{
  "transaction": {
    "transaction_id": "POS-TC10-001",
    "transaction_datetime": "2026-06-07T14:23:55+08:00",
    "merchant_code": "MRC123",
    "branch_code": "BR001",
    "pos_device_id": "POS01",
    "invoice_type": "OR",
    "items": [
      {
        "sku": "SKU001",
        "description": "Product A",
        "qty": 1,
        "unit_price": 100
      }
    ],
    "totals": {
      "gross": 100,
      "net": 100
    },
    "payment": {
      "method": "CASH",
      "amount": 100
    }
  }
}
```

**Expected:**

- `status`: `"accepted"`
- `processing_status`: `"queued"`
- Response includes `bridge_transaction_id`

---

### TC-11: Full Sale Object

**Steps:**

1. Submit a complete sale object including `customer`, discounts, VAT breakdown, `references`, and `metadata` as documented in the [full example](../vendor-api.md#full-example).

**Expected:**

- Transaction accepted
- No validation errors
- Optional fields preserved in processing

---

### TC-12: Multiple Items

**Steps:**

1. Submit a transaction with three or more line items in the `items` array (varying `sku`, `qty`, and `unit_price`).

**Expected:**

- Transaction accepted
- All items in the array processed correctly
- Totals align with line items

---

### TC-13: Different Payment Methods

**Steps:**

Submit separate transactions (unique `transaction_id` each) testing each payment method:

| Payment type | `payment.method` | Notes |
|--------------|------------------|-------|
| CASH | `CASH` | Standard cash payment |
| CARD | `CARD` | Include `payment.details.card_type`, `card_last4` |
| E-WALLET | `E-WALLET` | Include `payment.details.wallet_provider` |
| SPLIT PAYMENT | `SPLIT` | Multiple payment components in `payment.details` |

**Expected:**

- Each transaction accepted
- Payment breakdown preserved in the submitted payload

---

## 3. Validation & Error Handling Tests

See [Error Format](../pos-developer-integration-guide.md#error-format) and [Error handling](../vendor-api.md#7-error-handling).

### TC-20: Missing Required Field

**Steps:**

1. Submit a transaction with `totals.net` removed from the sale object.

**Expected:**

- HTTP `400`
- `error`: `"validation_error"`
- `fields`: `["totals.net"]`

Example response:

```json
{
  "status": "rejected",
  "error": "validation_error",
  "message": "Missing required field: totals.net",
  "fields": ["totals.net"]
}
```

---

### TC-21: Invalid Date Format

**Steps:**

1. Submit a transaction with `transaction_datetime` in an invalid format (e.g. `"07/06/2026 14:23"` instead of ISO 8601 with timezone).

**Expected:**

- Request rejected with a date format validation error
- HTTP `400`

---

### TC-22: Negative Quantity

**Steps:**

1. Submit a transaction with `qty: -1` on one or more line items (unless testing TC-71 return/refund scenario).

**Expected:**

- Request rejected
- Validation error referencing the invalid quantity

---

### TC-23: Zero or Negative Price

**Steps:**

1. Submit a transaction with `unit_price: 0` or `unit_price: -50` on a line item.

**Expected:**

- Request rejected
- Validation error referencing the invalid price

---

### TC-24: Invalid Merchant/Branch Code

**Steps:**

1. Submit a valid sale object using a `merchant_code` or `branch_code` not assigned to your vendor account.

**Expected:**

- HTTP `403`
- Message indicating merchant not found (e.g. `"Merchant not found"`)

---

## 4. Duplicate Handling Tests

See [Duplicate Protection](../pos-developer-integration-guide.md#duplicate-protection) and [Idempotency & duplicates](../vendor-api.md#8-idempotency--duplicates).

### TC-30: Same `transaction_id` sent twice

**Steps:**

1. Submit a transaction with `transaction_id: "POS-DUP-001"`.
2. Record the `bridge_transaction_id` from the first response.
3. Submit the **identical** payload a second time.

**Expected:**

- Second request returns:

```json
{
  "status": "duplicate",
  "bridge_transaction_id": "EB-20260607-000001"
}
```

- Same `bridge_transaction_id` as the first submission

---

### TC-31: Same `transaction_id` but different data

**Steps:**

1. Submit a transaction with `transaction_id: "POS-DUP-002"` and specific item totals.
2. Re-submit with the same `transaction_id` but different `items` or `totals`.

**Expected:**

- Request rejected with conflict
- `error`: `"transaction_conflict"`
- HTTP `409`

---

## 5. Batch Submission Tests

Endpoint: `POST /transactions/batch` — see [Batch Submit](../pos-developer-integration-guide.md#batch-submit-optional).

### TC-40: Valid Batch

**Steps:**

1. Prepare 10 unique transactions.
2. Submit via `POST /transactions/batch`:

```json
{
  "batch_id": "BATCH-TC40-001",
  "transactions": [
    { "... sale 1 ..." },
    { "... sale 2 ..." }
  ]
}
```

**Expected:**

- All 10 transactions accepted
- Response `summary` shows correct counts (`total: 10`, `accepted: 10`, `rejected: 0`)
- Each result includes `bridge_transaction_id` and `processing_status: "queued"`

---

### TC-41: Batch with 1 invalid transaction

**Steps:**

1. Submit a batch of 5 transactions where one transaction is missing a required field (e.g. `totals.net`).

**Expected:**

- Batch request accepted at the HTTP level
- Invalid item flagged in `results` with rejection details
- Valid transactions in the batch still accepted and queued
- `summary.rejected` reflects the failed count

---

### TC-42: Large Batch (100–500 transactions)

**Steps:**

1. Submit a batch containing 100 to 500 unique transactions.
2. Monitor response time and completion status.

**Expected:**

- No timeout
- All valid transactions queued
- `summary` totals match submitted count

---

## 6. Status Retrieval Tests

Endpoint: `GET /transactions/{bridge_transaction_id}` — see [Check Transaction Status](../pos-developer-integration-guide.md#check-transaction-status).

### TC-50: Query valid `bridge_transaction_id`

**Steps:**

1. Submit a transaction and capture `bridge_transaction_id`.
2. Call `GET /transactions/{bridge_transaction_id}`.
3. Poll until EIS processing completes (or use webhooks in parallel).

**Expected:**

- Returns current `processing_status` (e.g. `queued`, `sent`)
- Includes `eis_status` and `eis_reference_no` when BIR acknowledgment is available
- Response includes `logs` array with processing events

---

### TC-51: Query invalid ID

**Steps:**

1. Call `GET /transactions/EB-INVALID-000000` (or a non-existent ID).

**Expected:**

- HTTP `404`
- Appropriate not-found error message

---

## 7. Webhook Tests

See [Webhooks](../pos-developer-integration-guide.md#webhooks-recommended) and [Webhooks](../vendor-api.md#6-webhooks).

### TC-60: Configure Webhook

**Steps:**

1. Register your webhook endpoint via `POST /vendors/webhook`:

```json
{
  "webhook_url": "https://yourpos.com/eis/webhook",
  "secret": "your_secret"
}
```

**Expected:**

- HTTP `200`
- Webhook URL and secret saved successfully

---

### TC-61: Receive EIS acknowledgment webhook

**Steps:**

1. Configure webhook (TC-60).
2. Submit a transaction and wait for BIR EIS acknowledgment.
3. Verify your endpoint receives the callback payload.

**Expected:**

- Vendor receives payload containing:

```json
{
  "event": "transaction.eis_acknowledged"
}
```

- Payload includes `bridge_transaction_id`, `transaction_id`, `eis_status`, `eis_reference_no`, and `signature`

---

### TC-62: Invalid Webhook URL

**Steps:**

1. Attempt to configure a webhook with an invalid URL (e.g. malformed, non-HTTPS in production, or unreachable host).

**Expected:**

- HTTP `400`
- Validation error describing the invalid URL

---

### TC-63: Signature Verification

**Steps:**

1. Receive a webhook payload from EIS Bridge.
2. Compute HMAC-SHA256 of the payload body using your shared webhook secret.
3. Compare against the `signature` field in the payload.

**Expected:**

- Computed HMAC signature matches the `signature` value supplied by EIS Bridge
- Mismatched signatures are rejected by your handler

---

## 8. Compliance & Edge Case Tests

### TC-70: Void Transaction

**Steps:**

1. Submit an original sale transaction.
2. Submit a void transaction referencing the original via `references.original_transaction_id` and `references.return_or_void: true`.

**Expected:**

- Void transaction accepted
- Marked as void in processing
- Linked to the original transaction

---

### TC-71: Return/Refund

**Steps:**

1. Submit a return/refund transaction with negative `qty` on line items, or with `references.original_transaction_id` pointing to the original sale.

**Expected:**

- Return transaction accepted
- Negative quantity or original-sale reference preserved
- Linked to the source transaction

---

### TC-72: Offline POS (Delayed Transmission)

**Steps:**

1. Simulate an offline POS scenario: create a sale with `transaction_datetime` set to 24–48 hours in the past.
2. Submit the transaction to EIS Bridge at the delayed transmission time.

**Expected:**

- Transaction still accepted
- Still within T+3 compliance window
- `processing_status` transitions to `queued` normally

---

### TC-73: High Volume Spike

**Steps:**

1. Submit 1,000 transactions within a 5-minute window (single or batch requests).
2. Verify all submissions are acknowledged.

**Expected:**

- All transactions queued
- No dropped submissions
- No HTTP `5xx` errors under normal sandbox/production capacity

---

## 9. Security Tests

### TC-80: Missing Authorization Header

**Steps:**

1. Send any API request without the `Authorization` header.

**Expected:**

- HTTP `401`
- Unauthorized error response

---

### TC-81: Tampered Payload

**Steps:**

1. If your integration signs outbound payloads, submit a request with an altered body after signing.
2. Alternatively, modify a field in transit before EIS Bridge receives it.

**Expected:**

- Request rejected with signature mismatch (when vendor signing is enabled)
- No partial processing of tampered data

---

### TC-82: SQL/Script Injection Attempt

**Steps:**

1. Submit transactions with malicious strings in text fields (e.g. `description`, `customer.name`):

```
'; DROP TABLE transactions; --
<script>alert('xss')</script>
```

**Expected:**

- Input sanitized or rejected
- No script execution or database side effects
- Safe error response if rejected

---

## 10. Go-Live Certification Checklist

Vendor must pass all integration test areas before production certification:

- [ ] Single transaction submission (TC-10, TC-11, TC-12, TC-13)
- [ ] Batch submission (TC-40, TC-41, TC-42)
- [ ] Status retrieval (TC-50, TC-51)
- [ ] Duplicate handling (TC-30, TC-31)
- [ ] Error handling (TC-20 through TC-24)
- [ ] Webhook acknowledgment (TC-60 through TC-63)
- [ ] Offline/T+3 scenario (TC-72)
- [ ] High-volume test (TC-73)
- [ ] Refund/Void handling (TC-70, TC-71)
- [ ] Security tests (TC-80 through TC-82)

> **Note:** Once all items pass → Vendor is certified for EIS Bridge Production.

---

## Related Documentation

- [POS Developer Integration Guide](../pos-developer-integration-guide.md) — quick-start walkthrough
- [EIS Bridge Vendor API](../vendor-api.md) — full endpoint and error reference
- [Standard Sale Object schema](../schemas/sale-object.schema.json) — machine-readable JSON Schema
- [Postman Collection v1.0](../postman/EIS-Bridge-API-v1.postman_collection.json) — ready-to-import sample requests

---

© 2026 EIS Bridge
