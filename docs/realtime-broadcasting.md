# Real-time Broadcasting

EIS Bridge admin UI receives live updates for queue depth, alerts, invoice status, and merchant activity via Laravel broadcasting and Laravel Echo (Pusher).

## Channels and events

| Channel | Event | Payload |
|---------|-------|---------|
| `queues` | `queues.updated` | `{ queues: { [name]: { waiting, processing } } }` |
| `alerts` | `alerts.created` | Alert object (same shape as `/api/admin/alerts`) |
| `invoices.{id}` | `invoice.updated` | Invoice status fields |
| `merchants.{id}.activity` | `merchant.activity` | `{ event: { type, created_at, details, invoice_id? } }` |
| `analytics` | `analytics.updated` | `{ invoice_id, merchant_id, merchant_code, vendor_id, processing_status, eis_status, created_at, event_type }` |

All channels are **public** (`Channel`) for MVP simplicity. Channel authorization routes exist in `routes/channels.php` for future private-channel migration.

## Environment variables

### Backend (`api/.env`)

```env
# Dev without Pusher — events written to laravel.log
BROADCAST_CONNECTION=log

# Production / realtime
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-key
PUSHER_APP_SECRET=your-secret
PUSHER_APP_CLUSTER=mt1
```

`BROADCAST_DRIVER` is a legacy alias; Laravel 13 reads `BROADCAST_CONNECTION`.

### Frontend (Vite)

```env
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

If `VITE_PUSHER_APP_KEY` is empty, the admin SPA skips Echo and keeps existing HTTP polling.

## Pusher setup (free tier)

1. Create an app at [pusher.com](https://pusher.com).
2. Copy **app_id**, **key**, **secret**, and **cluster** into `.env`.
3. Set `BROADCAST_CONNECTION=pusher`.
4. Run `npm run build` (or `npm run dev`) so Vite picks up `VITE_PUSHER_*`.
5. Ensure `php artisan schedule:work` (or cron) runs `queues:broadcast` every 30 seconds for queue updates.

## Dev without Pusher

```env
BROADCAST_CONNECTION=log
# Leave VITE_PUSHER_APP_KEY empty
```

- Backend: `tail -f storage/logs/laravel.log` to see broadcast payloads.
- Frontend: Queue Monitor polls every 5s; Alerts Center polls every 60s; invoice/activity pages use React Query only; analytics dashboards stay static unless Echo is configured.

## Queue broadcasts

`php artisan queues:broadcast` runs on the scheduler every 30 seconds. It emits `QueueUpdated` only when queue depth changes **or** at least 30 seconds have passed since the last broadcast (avoids spam).

## Echo connection

`resources/js/admin/echo.js` initializes Echo when `VITE_PUSHER_APP_KEY` is set. Components import `subscribeToChannel` / `isEchoEnabled` dynamically.

Broadcast auth endpoint: `POST /broadcasting/auth` (Sanctum bearer token). Public channels do not require auth for subscribe, but the endpoint is registered for future private channels.

## Analytics broadcasts

`AnalyticsUpdated` is dispatched from `InvoiceBroadcaster` alongside `InvoiceStatusUpdated`:

- **`status_change`** — pipeline job status transitions (mapping, signing, transmission, EIS response).
- **`new_invoice`** — when a POS transaction is accepted and queued (`TransactionProcessor`).

The admin hook `hooks/useRealtimeAnalytics.js` listens on `analytics` / `.analytics.updated` and applies lightweight deltas to Invoice Analytics and Vendor Analytics dashboards. Without Echo, those pages load static data on range change only.

## Running the scheduler locally

```bash
php artisan schedule:work
```

Or trigger queue broadcasts manually:

```bash
php artisan queues:broadcast
```

## Alternative: Laravel Reverb

Self-hosted WebSockets via [Laravel Reverb](https://reverb.laravel.com) are not configured in this project. Pusher free tier is sufficient for development and small deployments.
