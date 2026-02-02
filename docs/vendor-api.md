# EIS Bridge Vendor API

> **Quick start:** See the [POS Developer Integration Guide](pos-developer-integration-guide.md) for a minimal walkthrough before using this full reference.

### Tools

- **[Postman Collection v1.0](postman/EIS-Bridge-API-v1.postman_collection.json)** — importable collection with sample requests for transaction submit, batch, status, and webhook configuration.
- **[POS Integration Test Cases (QA Suite v1.0)](qa/integration-test-cases-v1.md)** — QA test suite covering functional, validation, error, batch, webhook, and compliance scenarios for go-live certification.

The **EIS Bridge Vendor API** lets any POS/ERP vendor send standardized sales data to EIS Bridge, which then handles BIR EIS mapping, signing, and transmission.

---

## 1. Overview

### Base URLs

| Environment | Base URL |
|-------------|----------|
| Production | `https://api.eisbridge.ph/v1` |
| Sandbox | `https://sandbox.eisbridge.ph/v1` |

### Data format

| Setting | Value |
|---------|-------|
| Content-Type | `application/json` |
| Encoding | UTF-8 |

### Authentication

| Setting | Value |
|---------|-------|
| Scheme | API Key (per vendor) |
| Header | `Authorization: Bearer {VENDOR_API_KEY}` |

---

## 2. Authentication

### 2.1 API key

Each vendor receives a unique API key. Include it in every request:

```http
Authorization: Bearer VENDOR_API_KEY_123
Content-Type: application/json
```

If the API key is missing or invalid, the API returns:

```json
{
  "error": "unauthorized",
  "message": "Invalid or missing API key."
}
```

---

## 3. Core concepts

| Concept | Description |
|---------|-------------|
| **Vendor** | POS/ERP provider (you). |
| **Merchant** | Your client (taxpayer). |
| **Branch** | Physical location of merchant. |
| **Device** | POS terminal or instance. |
| **Transaction** | One sale/invoice/OR. |

During onboarding, EIS Bridge creates and shares the following identifiers for your integration:

| Identifier | Purpose |
|------------|---------|
| `merchant_code` | Identifies the merchant (taxpayer). |
| `branch_code` | Identifies the branch location. |
| `pos_device_id` | Identifies the POS terminal or instance. |

---

## 4. Standard POS → EIS Bridge Sale Object

This is the only JSON format you need to support.

### Full example

```json
{
  "transaction_id": "POS-123456",
  "transaction_datetime": "2026-06-07T14:23:55+08:00",
  "merchant_code": "MRC123",
  "branch_code": "BR001",
  "pos_device_id": "POS01",
  "invoice_type": "OR",
  "currency": "PHP",
  "customer": {
    "name": "Juan Dela Cruz",
    "tin": "123-456-789-000",
    "address": "Quezon City",
    "email": "juan@example.com",
    "mobile": "09171234567"
  },
  "items": [
    {
      "line_no": 1,
      "sku": "SKU001",
      "barcode": "1234567890123",
      "description": "Product A",
      "qty": 2,
      "unit": "PCS",
      "unit_price": 100.0,
      "discount": 0.0,
      "vat_rate": 12.0,
      "vat_exempt": false,
      "zero_rated": false
    }
  ],
  "totals": {
    "gross": 200.0,
    "discount": 0.0,
    "vatable_sales": 178.57,
    "vat_amount": 21.43,
    "vat_exempt_sales": 0.0,
    "zero_rated_sales": 0.0,
    "service_charge": 0.0,
    "net": 200.0
  },
  "payment": {
    "method": "CASH",
    "amount": 200.0,
    "details": {
      "card_type": null,
      "card_last4": null,
      "reference_no": null,
      "wallet_provider": null
    }
  },
  "references": {
    "original_transaction_id": null,
    "return_or_void": false,
    "return_reason": null
  },
  "metadata": {
    "pos_version": "1.0.0",
    "cashier_id": "C001",
    "cashier_name": "Maria Santos"
  }
}
```

### Required fields (minimum)

| Field | Notes |
|-------|-------|
| `transaction_id` | Unique per merchant + branch + device. |
| `transaction_datetime` | ISO 8601 datetime with timezone. |
| `merchant_code` | Assigned during onboarding. |
| `branch_code` | Assigned during onboarding. |
| `pos_device_id` | Assigned during onboarding. |
| `invoice_type` | e.g. `OR` |
| `items[]` | Each item requires `sku`, `description`, `qty`, `unit_price`. |
| `totals.gross` | Gross total. |
| `totals.net` | Net total. |
| `payment.method` | e.g. `CASH` |
| `payment.amount` | Payment amount. |

A machine-readable JSON Schema is available at [`schemas/sale-object.schema.json`](schemas/sale-object.schema.json).

---

## 5. Endpoints

All paths are relative to the base URL (e.g. `https://api.eisbridge.ph/v1`).

### 5.1 Submit transaction

**`POST /transactions`**

Submit a single sale/invoice to EIS Bridge.

#### Request

```http
POST /v1/transactions HTTP/1.1
Host: api.eisbridge.ph
Authorization: Bearer VENDOR_API_KEY_123
Content-Type: application/json
```

```json
{
  "transaction": {
    "...": "Standard Sale Object here"
  }
}
```

#### Response — success (accepted for processing)

```json
{
  "status": "accepted",
  "transaction_id": "POS-123456",
  "bridge_transaction_id": "EB-20260607-000001",
  "merchant_code": "MRC123",
  "branch_code": "BR001",
  "pos_device_id": "POS01",
  "processing_status": "queued",
  "message": "Transaction accepted for EIS processing."
}
```

#### Response — validation error

```json
{
  "status": "rejected",
  "error": "validation_error",
  "message": "Missing required field: totals.net",
  "fields": ["totals.net"]
}
```

---

### 5.2 Batch submit transactions

**`POST /transactions/batch`**

Send multiple transactions in one call.

#### Request body

```json
{
  "batch_id": "BATCH-20260607-001",
  "transactions": [
    { "...": "Standard Sale Object 1" },
    { "...": "Standard Sale Object 2" }
  ]
}
```

#### Response

```json
{
  "status": "accepted",
  "batch_id": "BATCH-20260607-001",
  "summary": {
    "total": 2,
    "accepted": 2,
    "rejected": 0
  },
  "results": [
    {
      "transaction_id": "POS-123456",
      "bridge_transaction_id": "EB-20260607-000001",
      "processing_status": "queued"
    },
    {
      "transaction_id": "POS-123457",
      "bridge_transaction_id": "EB-20260607-000002",
      "processing_status": "queued"
    }
  ]
}
```

---

### 5.3 Get transaction status

**`GET /transactions/{bridge_transaction_id}`**

Check EIS Bridge and BIR EIS status.

#### Example

```http
GET /v1/transactions/EB-20260607-000001 HTTP/1.1
Authorization: Bearer VENDOR_API_KEY_123
```

#### Response — success

```json
{
  "bridge_transaction_id": "EB-20260607-000001",
  "transaction_id": "POS-123456",
  "merchant_code": "MRC123",
  "branch_code": "BR001",
  "pos_device_id": "POS01",
  "processing_status": "sent",
  "eis_status": "acknowledged",
  "eis_reference_no": "EIS-INV-20260607-123456",
  "last_update": "2026-06-07T14:25:10+08:00",
  "logs": [
    {
      "timestamp": "2026-06-07T14:24:00+08:00",
      "event": "queued"
    },
    {
      "timestamp": "2026-06-07T14:24:30+08:00",
      "event": "sent_to_eis"
    },
    {
      "timestamp": "2026-06-07T14:25:10+08:00",
      "event": "eis_acknowledged"
    }
  ]
}
```

---

### 5.4 List transactions

**`GET /transactions`**

List transactions filtered by date, merchant, branch, and status.

#### Query parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `merchant_code` | Optional | Filter by merchant. |
| `branch_code` | Optional | Filter by branch. |
| `from` | Optional | Start of date range (ISO datetime). |
| `to` | Optional | End of date range (ISO datetime). |
| `status` | Optional | `queued` \| `sent` \| `acknowledged` \| `rejected` |
| `page` | Optional | Page number (default `1`). |
| `page_size` | Optional | Results per page (default `50`). |

#### Example

```http
GET /v1/transactions?merchant_code=MRC123&from=2026-06-07T00:00:00+08:00&to=2026-06-07T23:59:59+08:00&page=1&page_size=50
Authorization: Bearer VENDOR_API_KEY_123
```

---

## 6. Webhooks

Webhooks are optional but recommended. EIS Bridge can notify your system when BIR EIS responds.

### 6.1 Configure webhook URL

**`POST /vendors/webhook`**

```json
{
  "webhook_url": "https://posvendor.com/eisbridge/webhook",
  "secret": "your_webhook_secret"
}
```

### 6.2 Webhook payload example

```json
{
  "event": "transaction.eis_acknowledged",
  "bridge_transaction_id": "EB-20260607-000001",
  "transaction_id": "POS-123456",
  "merchant_code": "MRC123",
  "branch_code": "BR001",
  "eis_status": "acknowledged",
  "eis_reference_no": "EIS-INV-20260607-123456",
  "timestamp": "2026-06-07T14:25:10+08:00",
  "signature": "HMAC_SHA256_SIGNATURE"
}
```

Verify the `signature` field using your shared webhook secret (HMAC-SHA256).

---

## 7. Error handling

### 7.1 HTTP status codes

| Code | Meaning |
|------|---------|
| `200` | OK |
| `201` | Created / Accepted |
| `400` | Bad request (validation error) |
| `401` | Unauthorized (invalid/missing API key) |
| `403` | Forbidden (no access to merchant/branch) |
| `404` | Not found |
| `409` | Conflict (duplicate `transaction_id`) |
| `500` | Internal server error |

### 7.2 Error response format

```json
{
  "error": "validation_error",
  "message": "Missing required field: totals.net",
  "fields": ["totals.net"],
  "code": "EB-VAL-001"
}
```

---

## 8. Idempotency & duplicates

To avoid double-sending:

- `transaction_id` must be unique per merchant + branch + device.
- If the same `transaction_id` is sent again with identical data, EIS Bridge can return:

```json
{
  "status": "duplicate",
  "transaction_id": "POS-123456",
  "bridge_transaction_id": "EB-20260607-000001",
  "message": "Transaction already processed."
}
```

---

## 9. Vendor integration checklist

Before going live, a vendor should:

1. Obtain API key from EIS Bridge.
2. Receive merchant/branch/device codes for test accounts.
3. Implement Standard Sale Object mapping in POS/ERP.
4. Test:
   - Single transaction submit
   - Batch submit
   - Status retrieval
   - Webhook handling (if used)
5. Run UAT with sample merchants.
6. Move to production credentials.

---

© 2026 EIS Bridge
