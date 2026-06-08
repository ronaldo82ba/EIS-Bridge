# EIS Bridge — Observability & Operations

Phase 4 observability covers unified log access, proactive alerts, queue monitoring, and Laravel Horizon integration.

## Log types

| Type | Source | Admin endpoint | Retention guidance |
|------|--------|----------------|-------------------|
| **System** | `system_logs` table (Monolog `system_db` handler) with fallback to `storage/logs/laravel.log` | `GET /admin/logs/system` | DB: 90 days recommended; rotate file logs daily (Laravel `daily` channel) |
| **Transmission** | `transmission_logs` (EIS pipeline events) | `GET /admin/logs/transmission` | Keep 1 year for compliance; archive older rows |
| **Webhook** | `webhook_deliveries` | `GET /admin/logs/webhooks` | 90 days; vendor admins see only their vendor |
| **Audit** | `audit_logs` (admin/user actions) | `GET /admin/logs/audit` | 2 years minimum for security audits |

### CSV export

`GET /admin/logs/export?type=system|audit|transmission|webhooks` — **super_admin only**. Honors the same filter query params as list endpoints.

### System log DB handler

Warnings and errors are written to `system_logs` when `LOG_SYSTEM_DB=true` (default). Configure level via `LOG_SYSTEM_DB_LEVEL` (default: `warning`).

## Alert types

| Type | Trigger | Default threshold |
|------|---------|-------------------|
| `certificate_expiring` | **Moved to dedicated system** — see [certificate-alerts.md](./certificate-alerts.md) | N/A |
| `ptt_expiring` | `merchant_ptt.valid_to` within window | Same as certificates |
| `high_error_rate` | Invoice failures in last hour | `OBS_ERROR_RATE_THRESHOLD` (default 10%) |
| `queue_backlog` | Pending jobs per queue | `OBS_QUEUE_BACKLOG_THRESHOLD` (default 100) |

Alerts are deduplicated: no duplicate unresolved alert for the same type + entity within 24 hours.

### Alert API

- `GET /admin/alerts` — list (filter: `severity`, `type`, `active_only`)
- `GET /admin/alerts/summary` — counts by severity
- `POST /admin/alerts/{id}/acknowledge`
- `POST /admin/alerts/{id}/resolve`

## Scheduled tasks

| Command | Schedule | Purpose |
|---------|----------|---------|
| `observability:check` | Every 10 minutes | Run `AlertDetector` checks (PTT, error rate, queue) |
| `certificates:scan-expiry` | Daily at 01:00 | Certificate expiry alerts — see [certificate-alerts.md](./certificate-alerts.md) |
| `schedule:run` | Every minute (cron) | Laravel scheduler (required) |

Add to server crontab:

```bash
* * * * * cd /path/to/api && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoring API

| Endpoint | Description |
|----------|-------------|
| `GET /admin/monitoring/queues` | Queue depth, failed count per queue |
| `GET /admin/monitoring/workers` | Horizon supervisor status or DB worker heuristic |
| `GET /admin/monitoring/health` | DB, Redis, queue, disk health |

Access: **super_admin** and **support** roles.

## Laravel Horizon

### Install & configure

```bash
cd api
composer require laravel/horizon --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan horizon:install
php artisan migrate
```

> **Windows dev note:** Horizon requires `ext-pcntl` and `ext-posix` (Linux/macOS). Use `--ignore-platform-req` for local Composer install; run Horizon in production on Linux.

### Run Horizon

```bash
php artisan horizon
```

Horizon dashboard: `/horizon` (configurable via `HORIZON_PATH`).

Access is gated to `super_admin` and `support` via `HorizonServiceProvider`.

### Monitored queues

Configured in `config/observability.php`:

- `mapping`
- `signing`
- `transmission`
- `retry`
- `default`

## Configuration

Environment variables (see `.env.example`):

```
OBS_CERT_EXPIRY_WARNING_DAYS=30
OBS_CERT_EXPIRY_CRITICAL_DAYS=7
OBS_ERROR_RATE_THRESHOLD=10
OBS_QUEUE_BACKLOG_THRESHOLD=100
HORIZON_PATH=horizon
LOG_SYSTEM_DB=true
LOG_SYSTEM_DB_LEVEL=warning
ALERTS_ADMIN_EMAIL=admin@eis-bridge.test
```

Thresholds are centralized in `config/observability.php`.

## Testing alerts manually

```bash
php artisan observability:check
```

Verify alerts in admin UI at `/admin/alerts` or via API:

```bash
curl -H "Authorization: Bearer {token}" http://localhost/admin/alerts/summary
```

## PHPUnit

```bash
php artisan test --filter=AlertDetectorTest
php artisan test --filter=HealthCheckServiceTest
```
