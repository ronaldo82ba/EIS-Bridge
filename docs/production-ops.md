# Production Operations

## Cron / Scheduler

Laravel's scheduler must run every minute. Add to the server crontab for the `api/` directory:

```cron
* * * * * cd /path/to/api && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/api` with your deployment path (e.g. `/var/www/eis-bridge/api`).

### Scheduled commands

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `observability:check` | Every 10 minutes | Certificate/PTT expiry, error rate, queue backlog alerts |
| `licenses:check-renewals` | Daily | Expiring vendor/merchant licenses → alerts + billing events |

### Verify schedule registration

```bash
cd api
php artisan schedule:list
```

Expected output includes:

```
observability:check ........... Every ten minutes
licenses:check-renewals ....... Daily
```

### Manual runs

```bash
php artisan observability:check
php artisan licenses:check-renewals
php artisan licenses:check-renewals --days=14
```

## Queue Workers

See [queue-workers.md](queue-workers.md) for Horizon/Supervisor setup and Windows dev workarounds.

## EIS Integration

See [production-eis-setup.md](production-eis-setup.md) for BIR endpoint, sandbox mode, certificates, and schema validation.

## Monitoring Endpoints

Authenticated admin endpoints (`super_admin` or `support`):

- `GET /api/admin/monitoring/health`
- `GET /api/admin/monitoring/workers`
- `GET /api/admin/monitoring/queues`
- `GET /api/admin/alerts/summary`
