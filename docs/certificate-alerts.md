# Certificate Expiry Alerts

Dedicated PKI-style certificate expiry notifications, separate from observability dashboard alerts (`alerts` table).

## Overview

| Component | Purpose |
|-----------|---------|
| `certificate_alerts` table | One row per certificate + level (`expired`, `expiring_7`, `expiring_30`) |
| `certificates:scan-expiry` | Daily scanner that creates alerts and dispatches notifications |
| `SendCertificateAlertJob` | Sends admin email and vendor webhook |
| Admin UI | Dashboard panel, merchant banner, certificate alert history |

Observability `observability:check` no longer creates `certificate_expiring` alerts — use this system instead.

## Alert levels

| Level | When triggered |
|-------|----------------|
| `expired` | Certificate `expires_at` is today or in the past |
| `expiring_7` | Expires within 7 days |
| `expiring_30` | Expires within 30 days (but more than 7 days out) |

Each `(certificate_id, level)` pair is unique — alerts are never duplicated for the same level.

## Scheduled task

```bash
# Registered in routes/console.php — daily at 01:00
php artisan certificates:scan-expiry
```

Requires queue worker for notification delivery:

```bash
php artisan queue:work database --queue=default,webhooks
```

## Configuration

```env
ALERTS_ADMIN_EMAIL=admin@eis-bridge.test
MAIL_MAILER=log
```

See `config/alerts.php`.

## Admin API

| Endpoint | Description |
|----------|-------------|
| `GET /api/admin/certificate-alerts` | Paginated list with `certificate.merchant` |
| `GET /api/admin/dashboard` | Includes `certificate_alerts.count` and `certificate_alerts.recent` (top 5) |
| `GET /api/admin/merchants/{id}` | `certificate.expiry_alert` — latest level |
| `GET /api/admin/certificates/{id}` | `alerts` — full history |

Filter certificate alerts: `?level=expiring_7`

## Vendor webhook

Event: `certificate.expiry_alert`

```json
{
  "event": "certificate.expiry_alert",
  "data": {
    "merchant": "ABC Store",
    "merchant_code": "ABC001",
    "level": "expiring_7",
    "expires_at": "2026-07-01"
  }
}
```

Delivered via existing `WebhookDeliveryJob` when the merchant's vendor has a `webhook_url` configured.

## Manual testing

1. Set `ALERTS_ADMIN_EMAIL` in `.env` and use `MAIL_MAILER=log`.
2. Create or update a merchant certificate with `expires_at` within 7 days.
3. Run:

```bash
cd api
php artisan certificates:scan-expiry
php artisan queue:work --once
```

4. Verify:
   - Row in `certificate_alerts`
   - Log mail in `storage/logs/laravel.log`
   - Dashboard shows Certificate Alerts panel
   - Merchant detail shows amber banner
   - Certificate viewer shows alert history

## PHPUnit

```bash
php artisan test --filter=ScanCertificateExpiryTest
php artisan test --filter=CertificateAlertTest
php artisan test --filter=AdminCertificateAlertTest
```
