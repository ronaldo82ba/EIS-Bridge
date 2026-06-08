# EIS Bridge — Security

Phase 5 security hardening covers API key rotation, rate limiting, IP whitelisting, encrypted certificate storage, and role-based access control.

## API key rotation

Vendor API keys for `/v1/*` are stored as **HMAC-SHA256** hashes (keyed with `APP_KEY`). Plaintext keys are never persisted after initial creation or rotation.

### Rotate a vendor key (admin)

```http
POST /api/admin/vendors/{id}/rotate-api-key
Authorization: Bearer {admin_token}
```

Response includes the new plaintext key **once**. The previous key remains valid for the grace period configured by `API_KEY_GRACE_HOURS` (default **24 hours**).

Every rotation is recorded in `audit_logs` with action `vendor.api_key_rotated`.

### Revoke all admin tokens

```http
POST /api/admin/tokens/revoke-all
Authorization: Bearer {admin_token}
```

Deletes all Sanctum personal access tokens for the authenticated user. Admin token lifetime is controlled by `SANCTUM_TOKEN_EXPIRATION` (minutes, default **480**).

## Rate limits

| Limiter | Scope | Default | Env var |
|---------|-------|---------|---------|
| `vendor-api` | All `/v1/*` (per vendor) | 120/min | `VENDOR_API_RATE_LIMIT` |
| `vendor-transactions` | `POST /v1/transactions*` | 60/min | `VENDOR_TRANSACTION_RATE_LIMIT` |
| `admin-api` | All `/admin/*` (per user) | 60/min | `ADMIN_API_RATE_LIMIT` |
| `login` | `POST /admin/login` (per IP) | 5/min | `LOGIN_RATE_LIMIT` |

Exceeded limits return HTTP **429** with:

```json
{
  "error": "too_many_requests",
  "message": "Rate limit exceeded."
}
```

## IP whitelist setup

IP whitelisting is **opt-in**. If a vendor has no active whitelist entries, all IPs are allowed.

### Admin endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/admin/vendors/{vendor}/ip-whitelist` | List entries |
| POST | `/api/admin/vendors/{vendor}/ip-whitelist` | Add entry (`ip_address`, optional `label`) |
| DELETE | `/api/admin/vendors/{vendor}/ip-whitelist/{id}` | Remove entry |

`ip_address` accepts exact IPs or CIDR notation (e.g. `203.0.113.0/24`).

Vendor API requests blocked by the whitelist receive HTTP **403**:

```json
{
  "error": "forbidden",
  "message": "Request IP is not whitelisted for this vendor."
}
```

## Certificate storage architecture

| Item | Storage |
|------|---------|
| Certificate files | `storage/app/private/certificates/{merchant_id}/{uuid}.pfx` (disk: `CERTIFICATE_DISK`, default `local`) |
| File path in DB | Encrypted with `Crypt::encryptString()` |
| Password in DB | Encrypted with `Crypt::encryptString()` |

Files are **never** served from `public/`. Upload via:

```http
POST /api/admin/certificates
```

`CertificateStorageService` validates `.pfx`, `.p12`, `.pem` extensions and includes a virus-scan stub for production integration.

## RBAC matrix

| Resource / action | super_admin | vendor_admin | support |
|-------------------|:-----------:|:------------:|:-------:|
| Vendors list (all) | ✅ | ❌ | ✅ read |
| Vendor detail (own) | ✅ | ✅ | ✅ read |
| Vendor create | ✅ | ❌ | ❌ |
| API key rotation | ✅ | ✅ own vendor | ❌ |
| IP whitelist manage | ✅ | ✅ own vendor | ❌ |
| Merchants / branches / invoices | ✅ | ✅ scoped | ✅ read |
| Certificate upload | ✅ | ✅ scoped | ❌ |
| User management | ✅ | ❌ | ❌ |
| License plans create | ✅ | ❌ | ❌ |
| Horizon / queue admin | ✅ | ❌ | ❌ |
| Retry jobs | ✅ | ❌ | ✅ |
| Resend webhooks | ✅ | ❌ | ✅ |
| Acknowledge alerts | ✅ | ❌ | ✅ |

Support write mutations are gated by `SupportWriteAction` enum: `acknowledge_alert`, `retry_job`, `resend_webhook`.

## Security headers

Responses from `/v1/*` and `/api/admin/*` include:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`

## Environment variables

```env
API_KEY_GRACE_HOURS=24
VENDOR_API_RATE_LIMIT=120
VENDOR_TRANSACTION_RATE_LIMIT=60
ADMIN_API_RATE_LIMIT=60
LOGIN_RATE_LIMIT=5
CERTIFICATE_DISK=local
SANCTUM_TOKEN_EXPIRATION=480
```
