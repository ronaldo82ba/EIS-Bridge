# Phase 1 Laravel Engine Skeleton — Audit Checklist

Audit date: 2026-06-08. This document maps the Phase 1 skeleton spec to the current `api/` codebase.

Legend: ✅ Complete · ⚠️ Present with intentional deviation · ❌ Missing

---

## 1. Sanctum + User roles

| Skeleton item | Status | Actual location / notes |
|---|---|---|
| Sanctum installed | ✅ | `composer.json`, `vendor/laravel/sanctum`, `config/sanctum.php` |
| `personal_access_tokens` migration | ✅ | `database/migrations/0001_01_01_000010_phase5_security.php` |
| User `HasApiTokens` | ✅ | `app/Models/User.php` |
| `role` + `vendor_id` columns | ✅ | `database/migrations/0001_01_01_000009_phase2_schema.php` |
| `isSuperAdmin()` / `isVendorAdmin()` / `isSupport()` | ✅ | `app/Models/User.php` (+ `hasAdminAccess()`, `toAdminArray()`) |
| Role default `support` | ⚠️ | Migration default is `super_admin`; seeders create `super_admin` test users |
| Sanctum guard in `config/auth.php` | ✅ | `guards.sanctum` |

---

## 2. Core models + migrations

| Skeleton entity | Status | Actual table / model | Notes |
|---|---|---|---|
| Vendor (`name`, `code`, `api_key`, `status`) | ⚠️ | `vendors` / `app/Models/Vendor.php` | No `code` column; uses `name` + `id`. `api_key` is HMAC-hashed (Phase 5). |
| Merchant | ✅ | `merchants` / `app/Models/Merchant.php` | Uses `merchant_code` (not `code`). Added `tin`, `address`, `status` in phase2 migration. |
| Branch (`merchant_id`+`code` unique) | ✅ | `branches` / `app/Models/Branch.php` | Column is `branch_code`; unique index in `0001_01_01_000012_phase1_skeleton_constraints.php`. |
| Device (`branch_id`+`pos_device_id` unique) | ✅ | `devices` / `app/Models/Device.php` | `pos_device_id` matches skeleton; unique index added in phase1 constraints migration. |
| Certificate | ⚠️ | `merchant_certificates` / `app/Models/MerchantCertificate.php` | Skeleton name `Certificate` → `MerchantCertificate`. Extra `filename`, `uploaded_by`. |
| Ptt | ⚠️ | `merchant_ptt` / `app/Models/MerchantPtt.php` | One PTT per merchant (`merchant_id` unique). Extra `status` column. |
| WebhookDelivery | ⚠️ | `webhook_deliveries` / `app/Models/WebhookDelivery.php` | No `payload` column; stores `request_url`, `invoice_id`, `success`, `delivered_at`. |
| AuditLog | ✅ | `audit_logs` / `app/Models/AuditLog.php` | Extra `ip_address`. |
| Invoice (evolved) | ✅ | `invoices` / `app/Models/Invoice.php` | Phase 2+ fields preserved; not part of original skeleton but not broken. |

---

## 3. Admin routes

| Skeleton item | Status | Actual |
|---|---|---|
| Routes under `auth:sanctum` + `admin` prefix | ✅ | `routes/admin.php`, registered in `bootstrap/app.php` at `/api/admin/*` |
| Routes in `api.php` | ⚠️ | Dedicated `routes/admin.php` (keeps vendor `/v1/*` separate) |
| `POST /login` (Sanctum token) | ✅ | `AuthController@login` + `throttle:login` |
| `POST /logout`, `GET /me` | ✅ | `AuthController` |
| `GET /dashboard` | ✅ | `DashboardController@index` |
| `apiResource` vendors/merchants/branches/devices | ✅ | Full CRUD with policies + `AdminScope` |
| `apiResource` invoices (index, show) | ✅ | `InvoiceController` |
| `apiResource` certificates (index, store, show, destroy) | ✅ | `CertificateController` (store/delete implemented) |
| `apiResource` webhooks (index, store, show, update) | ⚠️ | `index`, `show`, `update` on `Vendor` model; no separate `store` (webhook config is per-vendor) |
| `apiResource` audit-logs (index, show) | ✅ | `Logs\AuditLogController` + alias `GET /logs/audit` for SPA |
| Extra Phase 2–5 routes | ⚠️ | Queues, monitoring, billing, IP whitelist, users — extensions beyond skeleton |

**SPA alignment:** React admin `baseURL` is `/api/admin` (`resources/js/admin/services/api.js`).

---

## 4. Admin controllers

| Controller | Status | File |
|---|---|---|
| AuthController (login/logout/me) | ✅ | `app/Http/Controllers/Admin/AuthController.php` |
| DashboardController | ✅ | `app/Http/Controllers/Admin/DashboardController.php` |
| VendorController | ✅ | CRUD + `rotateApiKey` |
| MerchantController | ✅ | CRUD + vendor scoping |
| BranchController | ✅ | CRUD |
| DeviceController | ✅ | CRUD |
| InvoiceController | ✅ | index, show |
| CertificateController | ✅ | index, store, show, destroy |
| WebhookController | ✅ | index, show, update |
| AuditLogController | ✅ | `app/Http/Controllers/Admin/Logs/AuditLogController.php` |
| Base `AdminController` + policies | ✅ | `AuthorizesRequests`, Gate policies registered in `AppServiceProvider` |

---

## 5. ApiKeyMiddleware (vendor API)

| Item | Status | Location |
|---|---|---|
| Middleware class | ✅ | `app/Http/Middleware/ApiKeyMiddleware.php` |
| Registered as `api_key` alias | ✅ | `bootstrap/app.php` |
| Applied to `/v1/*` vendor routes | ✅ | `routes/api.php` |
| Hashed keys + rotation grace | ✅ | `VendorApiKeyService` + phase5 migration |
| Backward compat plain key at seed | ✅ | `VendorSeeder` assigns `SANDBOX_API_KEY` / `VENDOR_API_KEY_123` |

---

## 6. Middleware registration (Laravel 11+)

| Middleware | Status | `bootstrap/app.php` alias |
|---|---|---|
| `api_key` | ✅ | `ApiKeyMiddleware` |
| `admin` | ✅ | `AdminMiddleware` (uses `hasAdminAccess()`) |
| `vendor.ip` | ✅ | `EnsureVendorIpAllowed` |
| `role` | ✅ | `EnsureRole` |
| `support.write` | ✅ | `EnsureSupportWriteAction` |
| `security.headers` | ✅ | `SecurityHeadersMiddleware` |

---

## 7. Seeders & policies

| Item | Status | Notes |
|---|---|---|
| `DatabaseSeeder` | ✅ | `VendorSeeder`, `AdminUserSeeder`, `LicensePlanSeeder` |
| Super admin test user | ✅ | `super_admin@eis-bridge.test` / `admin@eisbridge.ph` — password `password` |
| Sandbox vendor + merchant + branch + device | ✅ | `VendorSeeder` |
| Vendor scoping policies | ✅ | `VendorPolicy`, `MerchantPolicy`, `BranchPolicy`, `DevicePolicy`, `InvoicePolicy`, `CertificatePolicy` |
| `AdminScope` helper | ✅ | `app/Support/AdminScope.php` |

---

## 8. Verification (2026-06-08)

```bash
cd api
php artisan migrate:fresh --seed --force
php artisan route:list --path=admin
php artisan serve
```

**Login (curl):**

```bash
curl -s -X POST http://127.0.0.1:8000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@eisbridge.ph","password":"password"}'
```

**Dashboard:**

```bash
TOKEN="<token from login>"
curl -s http://127.0.0.1:8000/api/admin/dashboard \
  -H "Authorization: Bearer $TOKEN"
```

**Vendor API (unchanged):**

```bash
curl -s http://127.0.0.1:8000/v1/transactions \
  -H "Authorization: Bearer VENDOR_API_KEY_123"
```

---

## 9. Intentional deviations from skeleton

1. **Admin route prefix `/api/admin`** — not `/admin` in `api.php`; separate `routes/admin.php` keeps vendor `/v1/*` isolated and matches the React SPA `baseURL`.
2. **No `vendors.code`** — merchants use `merchant_code`, branches use `branch_code`; vendor identified by `id` + `name`.
3. **`MerchantCertificate` not `Certificate`** — same table, evolved naming; route model binding uses `MerchantCertificate`.
4. **`WebhookDelivery` schema** — richer than skeleton (`request_url`, `invoice_id`, etc.) for observability.
5. **User role default `super_admin`** — safer for fresh installs; seeded users are explicit super admins.
6. **Phase 5 hashed API keys** — plain keys only returned once at creation/rotation; `VendorApiKeyService` validates with grace-period rotation.
7. **Extra routes** — queues, billing, monitoring, alerts, IP whitelist added in later phases; do not conflict with skeleton endpoints.

---

## 10. Files touched in this reconciliation

| File | Change |
|---|---|
| `app/Policies/Concerns/HandlesAdminRoles.php` | Fixed PSR-4 namespace |
| `database/migrations/0001_01_01_000012_phase1_skeleton_constraints.php` | Unique indexes for branch/device codes |
| `app/Http/Controllers/Admin/CertificateController.php` | Implemented store/destroy |
| `app/Http/Controllers/Admin/WebhookController.php` | Added `update` |
| `app/Http/Controllers/Admin/Logs/AuditLogController.php` | Added `show` |
| `app/Services/Certificate/CertificateStorageService.php` | Added `delete()` |
| `app/Http/Middleware/AdminMiddleware.php` | Uses `hasAdminAccess()` |
| `routes/admin.php` | Certificate `only()`, webhook update, audit-logs show |
| `database/seeders/AdminUserSeeder.php` | Both test admin emails |
| `docs/phase-1-skeleton.md` | This checklist |
