# EIS Bridge — POS Developer Integration Guide

**Quick Start Guide for POS & ERP Vendors**

---

## Overview

EIS Bridge lets POS and ERP vendors connect to the BIR Electronic Invoicing System **without changing POS source code**. Map each sale to the **Standard Sale Object**, POST it to the Vendor API, and receive immediate async acceptance — EIS Bridge handles:

- JSON → BIR EIS mapping
- Digital signing (JWS)
- Queued transmission to BIR
- Retries and T+3 compliance
- Acknowledgment tracking (poll or webhooks)

**Merchant-side BIR requirements still apply:** each merchant taxpayer completes EIS registration, EIS CERT, and Permit to Transmit (PTT) on [eis-cert.bir.gov.ph](https://eis-cert.bir.gov.ph/). EIS Bridge guides the technical workflow; it does not replace taxpayer registration.

This guide shows how to integrate in minutes.

---

## Authentication

All requests require your Vendor API Key:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

### Base URLs

| Environment | Base URL | Status |
|-------------|----------|--------|
| Sandbox | `https://sandbox.eisbridge.com/v1` | Provisioned on request during vendor onboarding |
| Production | `https://api.eisbridge.com/v1` | Available after vendor certification |

Contact [support@eisbridge.ph](mailto:support@eisbridge.ph) for sandbox credentials, or run the API locally (see root README).

---

## Standard Sale Object — Minimum Required Fields

This is the **only JSON format** your POS needs to produce. Send this structure to EIS Bridge:

```json
{
  "transaction_id": "POS-10001",
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
```

Everything else is optional. Full field reference: [vendor-api.md](vendor-api.md) · JSON Schema: [schemas/sale-object.schema.json](schemas/sale-object.schema.json)

---

## Submit a Transaction (async acceptance)

**`POST /transactions`**

EIS Bridge accepts the transaction immediately and processes BIR transmission in the background. Checkout does not wait on BIR response time.

```http
POST /v1/transactions HTTP/1.1
Host: api.eisbridge.com
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

Request body:

```json
{
  "transaction": { "... sale object ..." }
}
```

Response:

```json
{
  "status": "accepted",
  "bridge_transaction_id": "EB-20260607-000001",
  "processing_status": "queued",
  "message": "Transaction accepted for EIS processing."
}
```

---

## Check Transaction Status

**`GET /transactions/{bridge_transaction_id}`**

```http
GET /v1/transactions/EB-20260607-000001 HTTP/1.1
Authorization: Bearer YOUR_API_KEY
```

Response:

```json
{
  "bridge_transaction_id": "EB-20260607-000001",
  "transaction_id": "POS-10001",
  "processing_status": "sent",
  "eis_status": "acknowledged",
  "eis_reference_no": "EIS-INV-20260607-123456"
}
```

When `eis_status` is `acknowledged`, BIR EIS has accepted the invoice. Persist `eis_reference_no` for audit and customer receipts.

---

## Batch Submit (Optional)

**`POST /transactions/batch`**

```json
{
  "batch_id": "BATCH-001",
  "transactions": [
    { "... sale 1 ..." },
    { "... sale 2 ..." }
  ]
}
```

---

## Webhooks (Recommended)

Configure webhooks to avoid polling. EIS Bridge notifies your system when BIR acknowledges a transaction.

### Configure webhook

**`POST /vendors/webhook`**

```json
{
  "webhook_url": "https://yourpos.com/eis/webhook",
  "secret": "your_secret"
}
```

### Webhook payload example

```json
{
  "event": "transaction.eis_acknowledged",
  "transaction_id": "POS-10001",
  "bridge_transaction_id": "EB-20260607-000001",
  "eis_status": "acknowledged",
  "eis_reference_no": "EIS-INV-20260607-123456"
}
```

---

## Error Format

```json
{
  "error": "validation_error",
  "message": "Missing required field: totals.net",
  "fields": ["totals.net"]
}
```

---

## Duplicate Protection

If the same `transaction_id` is sent twice with identical data:

```json
{
  "status": "duplicate",
  "bridge_transaction_id": "EB-20260607-000001"
}
```

Treat `duplicate` as success — the original transaction was already accepted.

---

## Developer Checklist

- [ ] Obtain sandbox API key and test merchant/branch/device codes
- [ ] Map POS sale → Standard Sale Object
- [ ] Send `POST /transactions` and verify `processing_status: queued`
- [ ] Store `bridge_transaction_id`
- [ ] Poll status or configure webhooks
- [ ] Run [QA Integration Test Cases v1.0](qa/integration-test-cases-v1.md)
- [ ] Complete [Certification Playbook](certification-playbook.md) for production go-live

---

## Next Steps

- **[EIS Bridge Vendor API](vendor-api.md)** — full API reference (endpoints, webhooks, error codes)
- **[Standard Sale Object schema](schemas/sale-object.schema.json)** — machine-readable JSON Schema
- **[Postman Collection v1.0](postman/EIS-Bridge-API-v1.postman_collection.json)** — ready-to-import requests. Set collection variables `BASE_URL`, `API_KEY`, and `BRIDGE_TRANSACTION_ID` before sending.
- **[Certification Playbook](certification-playbook.md)** — EIS CERT, PTT, and go-live steps
- **[Partner Program](partner-program.md)** — Vendor Edition and partner economics

---

## Compliance note

EIS Bridge is not affiliated with or accredited by the BIR. BIR certifies taxpayer systems, not software providers. Tax compliance remains the taxpayer's responsibility.

---

© 2026 EIS Bridge
