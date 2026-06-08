# Production EIS Setup Checklist

This guide covers BIR EIS integration, certificate signing, and environment configuration for production deployments.

## Environment Variables

| Variable | Dev default | Production |
|----------|-------------|------------|
| `EIS_SANDBOX_MODE` | `true` | `false` |
| `EIS_ENDPOINT` | Sandbox placeholder URL | URL from BIR EIS registration |
| `EIS_TIMEOUT` | `30` | `30` (adjust per BIR SLA) |
| `EIS_RETRY_MAX_ATTEMPTS` | `5` | `5` |
| `EIS_RETRY_BACKOFF` | `60,300,900,3600,7200` | Same or tuned |
| `EIS_MTLS_ENABLED` | `false` | `true` if BIR requires mTLS |
| `EIS_CLIENT_CERT_PATH` | empty | Absolute path to client PEM cert |
| `EIS_CLIENT_KEY_PATH` | empty | Absolute path to client PEM key |
| `CERTIFICATE_DISK` | `local` | `local` or S3-compatible disk |
| `QUEUE_CONNECTION` | `database` | `redis` (recommended with Horizon) |

Copy from `api/.env.example` and set production values before go-live.

## Sandbox vs Production

### Sandbox mode (`EIS_SANDBOX_MODE=true`, default)

- Transmission is **simulated** — no HTTP call to BIR.
- Signing falls back to a sandbox signature when no merchant certificate is configured.
- Safe for local development and integration testing.

### Production mode (`EIS_SANDBOX_MODE=false`)

- `EisClient` performs a real HTTP POST to `EIS_ENDPOINT`.
- Optional mTLS when `EIS_MTLS_ENABLED=true` and cert/key paths are set.
- Merchant certificates are **required** for signing (no sandbox fallback).
- Connection and rejection errors are logged to `transmission_logs`.

## BIR Schema Validation

After POS JSON is mapped, `MapInvoiceJob` validates output against:

- [`docs/schemas/bir-eis-invoice.schema.json`](schemas/bir-eis-invoice.schema.json)

Validation failures set `processing_status=failed` and log `bir_schema_validation_failed` in transmission logs.

Fields marked with `_comment` in the schema require confirmation against the official BIR EIS specification.

## BIR Endpoint Registration (placeholder)

> Official BIR EIS onboarding steps are not publicly documented in this repository.

Expected steps (confirm with BIR):

1. Register your organization and POS software with BIR EIS.
2. Obtain production API endpoint URL and credentials.
3. Obtain client certificate requirements (mTLS if applicable).
4. Set `EIS_ENDPOINT`, `EIS_SANDBOX_MODE=false`, and mTLS paths in `.env`.
5. Upload merchant signing certificates via admin API.

## Merchant Certificates

### Admin upload (production)

```http
POST /api/admin/merchants/{merchant_id}/certificate
Content-Type: multipart/form-data

file: <merchant.pfx>
password: <certificate-password>
expires_at: 2027-12-31  (optional)
```

Certificates are stored encrypted via `CertificateStorageService`.

### Test certificate (development only)

Seed a test certificate for the first merchant:

```bash
cd api
php artisan db:seed --class=CertificateTestSeeder
```

Or test signing directly:

```bash
php artisan eis:test-signing 1
```

Test certificates are generated at `storage/app/certificates/test/` with password `test-cert-password` (dev only).

## Verification

```bash
# Validate mapped output against BIR schema
php artisan test --filter=BirSchemaValidatorTest

# Test end-to-end signing
php artisan eis:test-signing 1

# Confirm scheduled tasks
php artisan schedule:list
```

## Related Documentation

- [Queue Workers](queue-workers.md)
- [Production Operations](production-ops.md)
- [Standard Sale Object schema](schemas/sale-object.schema.json)
- [BIR EIS Invoice schema](schemas/bir-eis-invoice.schema.json)
