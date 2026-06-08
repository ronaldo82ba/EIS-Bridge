# EIS Bridge v1.0.0-rc1

Release candidate 1 for the Tier 1–3 reliability and admin upgrade.

## Tier 1 — Invoice engine
- Deduplication index `invoices_dedup_index` on `transaction_id`, `merchant_code`, `branch_code`, `pos_device_id`
- Hardened map/sign/transmit/retry job pipeline; rejection short-circuit and atomic ID generation tests
- Production/sandbox guards and transmission retry deduplication

## Tier 2 — Admin UI
- Invoice search, webhooks, vendor services, status badges, realtime analytics hooks
- Sidebar navigation: Dashboard, Vendors, Merchants, Branches, Invoices, Alerts, Queues, Certificates, Webhooks, Billing, Logs, Monitoring, Settings

## Tier 3 — Quality & CI
- ESLint workflow (`lint.yml`) for admin JS
- PHPUnit workflow (PHP 8.3, `php artisan test`)
- Frontend build workflow (Node 20, `npm run build`)

## Validation (local RC1)
| Check | Result |
|-------|--------|
| PHPUnit | **142** tests, **142** passed, **710** assertions |
| ESLint | Pass |
| Vite build | Pass |

## Commits in this RC
- `cbf0340` — Harden invoice engine, admin UI, and dedup index for Tier 1-3 reliability
- `96196d6` — feat: Complete Tier 1-3 EIS Bridge upgrade (engine, admin UI, quality)

## Staging deployment (manual)
1. Pull `release/rc1` or tag `v1.0.0-rc1` on the staging host.
2. In `api/`: `composer install --no-dev --optimize-autoloader`
3. Copy `.env` for staging; ensure `APP_ENV=staging` and EIS endpoints are sandbox.
4. `php artisan migrate --force`
5. `npm ci && npm run build`
6. `php artisan config:cache route:cache view:cache`
7. Restart queue workers (`php artisan queue:restart`) and PHP-FPM / web server.
8. Smoke-test admin login, invoice list, and queue monitor.

## Git
- Branch: `release/rc1`
- Tag: `v1.0.0-rc1`
