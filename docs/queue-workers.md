# Queue Workers

EIS Bridge processes invoices through four queues:

| Queue | Job | Purpose |
|-------|-----|---------|
| `mapping` | `MapInvoiceJob` | POS JSON → BIR structure + schema validation |
| `signing` | `SignInvoiceJob` | Sign BIR JSON with merchant certificate |
| `transmission` | `TransmitInvoiceJob` | POST signed payload to BIR EIS |
| `retry` | `RetryFailedTransmissionJob` | Retry failed transmissions with backoff |

Horizon configuration is in `api/config/horizon.php`.

## Linux Production (Horizon + Supervisor)

### 1. Install Redis and set queue driver

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

### 2. Supervisor — Horizon (recommended)

Copy `deploy/supervisor/horizon.conf` to `/etc/supervisor/conf.d/` and adjust paths:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start eis-bridge-horizon
```

### 3. Supervisor — fallback without Horizon

If Horizon is unavailable, use `deploy/supervisor/eis-bridge-queues.conf` instead.

### 4. Monitor workers

```http
GET /api/admin/monitoring/workers
GET /api/admin/monitoring/queues
```

Requires `super_admin` or `support` role.

## Windows Development

**Horizon cannot run on Windows** — it requires `ext-pcntl` and `ext-posix`, which are not available on Windows PHP builds.

Use the database queue driver and `queue:work` instead:

```bash
cd api

# Process all compliance queues until empty (good for manual testing)
php artisan queue:work --queue=mapping,signing,transmission,retry,default --stop-when-empty

# Or listen continuously during dev (included in composer dev script)
php artisan queue:listen --tries=3 --timeout=120
```

The `composer dev` script already starts `queue:listen` alongside `artisan serve`.

## Health Checks

- `GET /api/admin/monitoring/workers` — reports Horizon status (Linux) or database worker heartbeat
- `GET /api/admin/monitoring/health` — overall system health
- `GET /api/admin/monitoring/queues` — queue depth and failed job counts

## Docker

No `docker-compose.yml` is included in this repository. Add a worker service when containerizing:

```yaml
worker:
  build: ./api
  command: php artisan horizon
  depends_on:
    - redis
    - app
```
