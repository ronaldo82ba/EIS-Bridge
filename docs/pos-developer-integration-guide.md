# EIS Bridge — POS Developer Integration Guide

**Quick Start Guide for POS & ERP Vendors**

---

## Overview

EIS Bridge allows any POS or ERP system to become BIR EIS-compliant without rewriting your software. You simply send standardized sales JSON to EIS Bridge — we handle:

- JSON → BIR EIS mapping
- Digital signing
- Transmission to BIR
- Queueing & retries
- T+3 compliance
- Acknowledgment tracking

This guide shows how to integrate in minutes.

---

## Authentication

All requests require your Vendor API Key:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

### Base URLs

| Environment | Base URL |
|-------------|----------|
| Sandbox | `https://sandbox.eisbridge.ph/v1` |
| Production | `https://api.eisbridge.ph/v1` |

---

## Standard Sale Object — Minimum Required Fields

Send this structure to EIS Bridge:

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

Everything else is optional.

---

## Submit a Transaction

**`POST /transactions`**

```http
POST /v1/transactions HTTP/1.1
Host: api.eisbridge.ph
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
  "processing_status": "queued"
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

If the same `transaction_id` is sent twice:

```json
{
  "status": "duplicate",
  "bridge_transaction_id": "EB-20260607-000001"
}
```

---

## Developer Checklist

- [ ] Add API key
- [ ] Map POS sale → EIS Bridge JSON
- [ ] Send `/transactions`
- [ ] Store `bridge_transaction_id`
- [ ] Poll status or use webhooks
- [ ] Go live

---

## Next Steps

- **[EIS Bridge Vendor API](vendor-api.md)** — full API reference (endpoints, webhooks, error codes, and integration checklist)
- **[Standard Sale Object schema](schemas/sale-object.schema.json)** — machine-readable JSON Schema for validation and tooling
- **[Postman Collection v1.0](postman/EIS-Bridge-API-v1.postman_collection.json)** — ready-to-import requests for all core endpoints. In Postman, choose **Import → Upload Files** and select the collection file. Set collection variables `BASE_URL`, `API_KEY`, and `BRIDGE_TRANSACTION_ID` before sending requests.
- **[POS Integration Test Cases (QA Suite v1.0)](qa/integration-test-cases-v1.md)** — structured test cases for connectivity, validation, batch, webhooks, compliance, and go-live certification

---

© 2026 EIS Bridge
