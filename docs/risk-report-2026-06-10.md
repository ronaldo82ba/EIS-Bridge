# EIS Bridge — Consolidated Risk Analysis Report

**Workspace:** `C:\laragon\www\EIS Bridge`  
**Repository:** [ronaldo82ba/EIS-Bridge](https://github.com/ronaldo82ba/EIS-Bridge)  
**Branch analyzed:** `release/rc1`  
**Analysis date:** June 10, 2026  
**Scope:** Read-only analysis; tests and lint executed locally

> **Update (June 10, 2026):** P0 fixes were applied the same day as this analysis. Vendor API tenant isolation (`TransactionController`, `TransactionProcessor`) and vendor suspension + license enforcement (`ApiKeyMiddleware`, `LicenseEnforcement` wired into the transaction path) are **resolved**. After P0 fixes, **150 PHPUnit tests pass** (was 142 before fixes; 8 new feature tests added in `VendorApiTenantIsolationTest`).

---

## Executive Summary

EIS Bridge is a well-structured Laravel 13 monorepo (static marketing at repo root, Laravel API in `api/`, React admin via Vite). The invoice processing pipeline (mapping → signing → transmission → retry → webhooks) is coherent, Horizon is configured for production queues, and **150 PHPUnit tests pass** with **ESLint clean** (post-P0 fixes).

The two **P0 go-live blockers** identified in the initial analysis — **multi-tenant isolation gaps on the Vendor API** and **unwired billing/license enforcement** — were **fixed on June 10, 2026**. Remaining highest risks are **operational/config mismatches**: Horizon missing `staging` environment, CI not running on `release/rc1`, composer lock drift, empty production `EIS_ENDPOINT`, and pipeline resilience gaps (Map/Sign job retries, webhook handling).

Overall posture: **strong test coverage and engine hardening for RC1**; P0 security/compliance controls are in place. **Production go-live should still address P1 config/CI items** before exposing multi-vendor traffic at scale.

---

## Current Issues (with severity: Critical/High/Medium/Low)

### Resolved (P0 — fixed June 10, 2026)

#### 1. Vendor API read endpoints lack tenant isolation — **RESOLVED**
- **Severity:** Critical (was)  
- **Status:** Fixed June 10, 2026  
- **File path(s):** `api/app/Http/Controllers/TransactionController.php`, `api/routes/api.php`, `api/tests/Feature/VendorApiTenantIsolationTest.php`  
- **Description:** `GET /v1/transactions/{bridgeTransactionId}` and `GET /v1/transactions` previously authenticated via API key but did not scope queries to the authenticated vendor.  
- **Fix applied:** `show()` and `index()` now scope invoices through the vendor's merchant codes via `whereIn('merchant_code', $vendor->merchants()->pluck('merchant_code'))`. Cross-vendor lookups return **404** (`not_found`). Feature tests assert isolation.

#### 2. Transaction ingestion does not validate merchant belongs to vendor — **RESOLVED**
- **Severity:** Critical (was)  
- **Status:** Fixed June 10, 2026  
- **File path(s):** `api/app/Services/TransactionProcessor.php`, `api/app/Http/Controllers/TransactionController.php`  
- **Description:** `processSingle()` previously accepted `$vendor` but did not use it; vendors could POST transactions for arbitrary `merchant_code` values.  
- **Fix applied:** Verifies `Merchant::where('merchant_code', ...)->where('vendor_id', $vendor->id)` before processing. Rejects unowned merchants with **403** / `merchant_not_owned`. Device-lock checks scoped to vendor-owned merchants.

#### 3. Suspended vendors can still call the Vendor API — **RESOLVED**
- **Severity:** High (was)  
- **Status:** Fixed June 10, 2026  
- **File path(s):** `api/app/Http/Middleware/ApiKeyMiddleware.php`  
- **Description:** API key validation previously returned any matching vendor without checking `status === 'suspended'`.  
- **Fix applied:** `ApiKeyMiddleware` rejects suspended vendors with **403** / `vendor_suspended`.

#### 4. License enforcement service exists but is never used — **RESOLVED**
- **Severity:** High (was)  
- **Status:** Fixed June 10, 2026  
- **File path(s):** `api/app/Services/Billing/LicenseEnforcement.php`, `api/app/Http/Middleware/ApiKeyMiddleware.php`, `api/app/Services/TransactionProcessor.php`  
- **Description:** `LicenseEnforcement` implemented `canVendorOperate()` / `canMerchantOperate()` but was not wired into middleware or the transaction processor.  
- **Fix applied:** Injected into `ApiKeyMiddleware` (vendor license check) and `TransactionProcessor` (merchant license check via `canMerchantOperate()`). Tests cover expired/suspended license scenarios.

---

### Open issues

#### 5. Horizon config missing `staging` environment (sandbox site)
- **Severity:** High  
- **File path(s):** `api/config/horizon.php` (lines 67–81), `api/.env.sandbox.example` (line 6: `APP_ENV=staging`)  
- **Description:** Sandbox deployment uses `APP_ENV=staging`, but Horizon only defines supervisors for `production` and `local`. On staging, Horizon falls back to `defaults` only—no environment-specific scaling—and behavior may differ from production/sandbox expectations documented in `docs/FORGE_DEPLOYMENT.md`.  
- **Recommended fix:** Add a `staging` block in `horizon.php` mirroring production (or map sandbox to `production` Horizon profile intentionally and document it).

#### 6. CI workflows do not run on `release/rc1`
- **Severity:** High  
- **File path(s):** `.github/workflows/phpunit.yml` (lines 4–6), `.github/workflows/build.yml` (lines 4–6), `.github/workflows/lint.yml` (lines 10–11)  
- **Description:** All workflows trigger only on `main`/`master`. Active branch is `release/rc1`; pushes/PRs on this branch **do not run PHPUnit, frontend build, or ESLint** in GitHub Actions.  
- **Recommended fix:** Add `release/**` or `release/rc1` to workflow `on.push`/`on.pull_request` branches; or merge RC through CI-enabled branches before release.

#### 7. Production env template has empty `EIS_ENDPOINT`
- **Severity:** High  
- **File path(s):** `api/.env.production.example` (line 72), `api/app/Services/Eis/EisClient.php` (lines 43–47)  
- **Description:** Production example sets `EIS_SANDBOX_MODE=false` but leaves `EIS_ENDPOINT=` blank. App boots (sandbox guard passes), but real transmission throws `RuntimeException('EIS endpoint is not configured.')` at runtime—silent misconfiguration until first live invoice.  
- **Recommended fix:** Add deploy-time validation (Artisan command or health check) requiring non-empty `EIS_ENDPOINT` when `EIS_SANDBOX_MODE=false`; document as mandatory in Forge checklist.

#### 8. Composer lock file out of sync with `composer.json`
- **Severity:** Medium  
- **File path(s):** `api/composer.json`, `api/composer.lock`  
- **Description:** `composer validate` reports: *"The lock file is not up to date with the latest changes in composer.json."* Reproducible installs and CI cache keys may drift.  
- **Recommended fix:** Run `composer update` (or targeted lock refresh) on a Linux/CI runner and commit the updated lock.

#### 9. Unbound version constraints for Horizon and Sanctum
- **Severity:** Medium  
- **File path(s):** `api/composer.json` (lines 11–12)  
- **Description:** `"laravel/horizon": "*"` and `"laravel/sanctum": "*"` allow any compatible release on fresh `composer update`, increasing surprise breakage risk. Lock currently pins Horizon `v5.47.2`, Sanctum `v4.3.2`.  
- **Recommended fix:** Pin to caret constraints (e.g. `^5.47`, `^4.3`) matching tested versions.

#### 10. Alert dedupe config inconsistency
- **Severity:** Medium  
- **File path(s):** `api/config/alerts.php` (line 5), `api/config/observability.php` (line 8), `api/app/Console/Commands/CheckLicenseRenewals.php` (line 154)  
- **Description:** `.env*` sets `ALERTS_DEDUPE_HOURS=1`, but `config/observability.php` hardcodes `'alert_dedupe_hours' => 24` (not env-driven). License renewal alerts use the 24h value, not the 1h env setting.  
- **Recommended fix:** Align `observability.alert_dedupe_hours` to `env('ALERTS_DEDUPE_HOURS', 1)` or remove duplicate config key.

#### 11. Mapping/signing jobs have no queue retries (`tries = 1`)
- **Severity:** Medium  
- **File path(s):** `api/app/Jobs/MapInvoiceJob.php` (line 24), `api/app/Jobs/SignInvoiceJob.php` (line 24), `deploy/supervisor/eis-bridge-queues.conf` (line 6: `--tries=3`)  
- **Description:** Transient DB/IO failures in mapping/signing mark invoices `failed` permanently with no automatic retry. Horizon defaults use `tries => 3`, but job-level `$tries = 1` overrides worker settings. Transmission retry path is robust; upstream stages are brittle.  
- **Recommended fix:** Increase `$tries` and add `$backoff` for Map/Sign jobs, or dispatch admin bulk retry on transient exceptions only.

#### 12. Webhook delivery lacks connection-error handling
- **Severity:** Medium  
- **File path(s):** `api/app/Jobs/WebhookDeliveryJob.php` (lines 54–88)  
- **Description:** HTTP POST has no try/catch for `ConnectionException`. Network failures may fail the job abruptly vs. structured retry via `$tries`/`backoff()`.  
- **Recommended fix:** Catch connection errors, log to `WebhookDelivery`, and `$this->release()` or `$this->fail()` consistently.

#### 13. Webhook configuration accepts arbitrary URLs without validation
- **Severity:** Medium  
- **File path(s):** `api/app/Http/Controllers/WebhookController.php` (lines 13–16)  
- **Description:** Vendor can set any `webhook_url` and `secret` with no URL scheme/host validation—SSRF risk if server-side requests are made to internal addresses.  
- **Recommended fix:** Validate HTTPS URLs, block private IP ranges, require URL parsing rules.

#### 14. npm critical vulnerability in dev dependency chain
- **Severity:** Medium (dev-only)  
- **File path(s):** `api/package.json` (line 13: `concurrently`), `npm audit` output  
- **Description:** `npm audit` reports **2 critical** issues via `shell-quote` ← `concurrently@9.2.1`. Dev dependency only; not in production bundle, but affects developer/CI supply chain.  
- **Recommended fix:** Upgrade `concurrently` to v10+ or run `npm audit fix`; re-run `npm run lint` and `npm run build`.

#### 15. Default sandbox API key in `.env.example`
- **Severity:** Medium  
- **File path(s):** `api/.env.example` (line 91), `api/database/seeders/VendorSeeder.php` (line 16)  
- **Description:** `SANDBOX_API_KEY=VENDOR_API_KEY_123` is a known default documented in `api/README.md`. Safe for local dev; dangerous if copied to any shared/staging host without rotation.  
- **Recommended fix:** Require explicit key generation on sandbox deploy; fail seed if default key detected outside `local` env.

#### 16. `composer audit` could not complete (network timeout)
- **Severity:** Low  
- **File path(s):** N/A (tooling)  
- **Description:** `composer audit` failed with curl timeout to packagist.org after ~10s. PHP dependency CVE status **could not be verified** in this run.  
- **Recommended fix:** Re-run in CI with retry; enable Dependabot or similar.

#### 17. Laravel framework patch update available
- **Severity:** Low  
- **File path(s):** `api/composer.lock` (Laravel `v13.14.0`)  
- **Description:** `composer outdated --direct` shows `13.14.0 → 13.15.0` patch available.  
- **Recommended fix:** Update after reviewing Laravel release notes; re-run full test suite.

#### 18. Horizon cannot run on Windows dev (missing `pcntl`/`posix`)
- **Severity:** Low  
- **File path(s):** Local PHP 8.3.30 (no `pcntl`/`posix`); `api/.env.example` (lines 123–127)  
- **Description:** Documented workaround (`QUEUE_CONNECTION=database` + `queue:work`) exists. Local dev cannot mirror production Horizon behavior on Windows.  
- **Recommended fix:** Document WSL/Docker for Horizon parity; no code change required.

#### 19. `public/build` absent locally
- **Severity:** Low  
- **File path(s):** `api/public/build/` (missing), `api/vite.config.js`, `deploy/forge-deploy-api.sh` (lines 26–28)  
- **Description:** No committed Vite build artifacts (correct for git). Local admin SPA requires `npm run build`. Forge deploy script runs build; local checkout without build shows unstyled admin until built.  
- **Recommended fix:** Ensure deploy script always runs; add health check for manifest presence post-deploy.

#### 20. ESLint/PHP lint not enforced on all PHP changes in CI
- **Severity:** Low  
- **File path(s):** `.github/workflows/lint.yml` (JS only), no Pint/PHPStan workflow  
- **Description:** `npm run lint` passes locally. PHP style/static analysis not in CI beyond PHPUnit.  
- **Recommended fix:** Add `laravel/pint --test` or PHPStan job to CI.

---

## Potential Future Issues (predictive)

#### P1. High coupling in transaction hot path → merge/regression risk
- **Severity:** Medium (predictive)  
- **File path(s):** `api/routes/api.php`, `api/app/Services/TransactionProcessor.php`, `api/app/Models/Vendor.php`, `api/app/Models/Invoice.php`  
- **Description:** Git history shows repeated edits to these files across Tier 1–3 work. Pipeline changes (dedup index, job refactor removing `ProcessInvoiceJob`) touch the same modules. Future EIS schema or vendor API changes will conflict here.  
- **Recommended fix:** Add integration tests for full vendor API contract; consider extracting vendor-scoping into a dedicated policy/service before next major feature.

#### P2. Billing subsystem complexity without test coverage
- **Severity:** Medium (predictive)  
- **File path(s):** `api/app/Services/Billing/BillingInvoiceGenerator.php`, `LicensePlanCatalog.php`, `MerchantLicenseService.php`, `VendorLicenseService.php`  
- **Description:** Only `SaasBillingService` has unit tests. Billing UI exists in admin React (`resources/js/admin/pages/Billing/`). Untested billing logic may still have revenue/compliance gaps at scale despite wired `LicenseEnforcement`.  
- **Recommended fix:** Add tests before expanding billing enforcement in production; wire enforcement incrementally.

#### P3. Certificate/signing path under-tested for production formats
- **Severity:** Medium (predictive)  
- **File path(s):** `api/app/Services/Signing/CertificateLoader.php` (lines 58–98), `JsonSigner.php`  
- **Description:** PKCS#12/PEM loading and mTLS paths (`EisClient.php` lines 109–127) lack dedicated unit tests. Production go-live depends on merchant cert formats and optional BIR mTLS.  
- **Recommended fix:** Add fixture-based tests for `.pfx`, expired certs, and mTLS file-not-found scenarios.

#### P4. EIS response parser fragility when BIR changes payload shape
- **Severity:** Medium (predictive)  
- **File path(s):** `api/app/Services/Eis/EisResponseParser.php` (lines 24–50)  
- **Description:** Parser uses heuristic field lookups (`reference_no`, nested `data.*`). Partial test coverage in `RejectionShortCircuitTest`. Real BIR production responses may differ from sandbox simulated shape.  
- **Recommended fix:** Capture production response samples (redacted) as contract tests; version the parser.

#### P5. Sandbox `APP_ENV=staging` vs production guard asymmetry
- **Severity:** Low (predictive)  
- **File path(s):** `api/app/Providers/AppServiceProvider.php` (lines 111–117)  
- **Description:** Guard only blocks `production` + sandbox mode. Misconfigured sandbox host with `APP_ENV=production` fails fast (good). Misconfigured production with `APP_ENV=staging` and `EIS_SANDBOX_MODE=true` would **not** trigger the guard—could simulate BIR on a prod hostname if env is wrong.  
- **Recommended fix:** Optional guard: refuse `EIS_SANDBOX_MODE=true` when `APP_URL` matches production domain pattern.

#### P6. Realtime/admin dependency on Pusher configuration
- **Severity:** Low (predictive)  
- **File path(s):** `api/.env.production.example` (lines 37–42), `api/config/broadcasting.php`, admin hooks (`useRealtimeAnalytics.js`)  
- **Description:** Production expects `BROADCAST_CONNECTION=pusher` with empty credentials in template. Missing Pusher config degrades admin realtime (queue depth, analytics) without blocking core API.  
- **Recommended fix:** Health check for broadcast connectivity; graceful UI fallback when Pusher unavailable.

#### P7. RC branch divergence from `master` without CI gate
- **Severity:** Medium (predictive)  
- **File path(s):** Git branches: `release/rc1`, `master`  
- **Description:** RC work may accumulate on `release/rc1` without automated CI until merge to `master`, increasing release-day surprise failures.  
- **Recommended fix:** Run full CI on release branches; require green checks before tag.

---

## Step-by-Step Findings (01–09)

| Step | Title | Summary |
|------|-------|---------|
| **01** | Initialize Deep System Context | Monorepo layout: root static site (`index.html`, `portal/`, `styles/`), `api/` Laravel 13 app, `deploy/` Forge scripts, `docs/`. Stack: PHP ^8.3, Laravel ^13.8, Horizon, Sanctum, Redis queues (prod), database queues (local), React 19 + Vite 8 + Ant Design admin SPA, Pusher broadcasting, GitHub Actions CI. Non-standard: no root `package.json` (Node only in `api/`). |
| **02** | Scan Structural Integrity | PSR-4 autoload paths valid (`App\` → `app/`). No `app/Actions/` directory. Removed `ProcessInvoiceJob` has no stale references. Duplicate audit logging layers (`Services/Audit/AuditLogger` wraps `Services/Security/AuditLogger`)—intentional, not orphaned. ~~`LicenseEnforcement` is orphaned/unused~~ → **wired June 10, 2026**. Composer lock out of sync. `public/build/` not present locally (expected). Legacy `store/` paths in git history absent from current tree. |
| **03** | Configuration & Environment Risks | Three env templates present and documented. Production sandbox boot guard works (`AppServiceProvider` + health). Risks: empty prod `EIS_ENDPOINT`, default `SANDBOX_API_KEY`, Horizon `staging` gap, dedupe config split, `.env` gitignored but present on disk (normal). Session/cache/queue correctly differ local (database) vs prod (redis). |
| **04** | Dependency Health | Laravel 13.14.0 installed; 13.15.0 patch available. Horizon/Sanctum use `*` constraints. npm: eslint 8 vs 10 latest, concurrently 9 vs 10. **`npm audit`: 2 critical (dev). `composer audit`: timeout—no result.** Lock drift warning from `composer validate`. |
| **05** | Pipeline & Queue Weak Points | 11 jobs across mapping/signing/transmission/retry/webhooks/bulk. Queue names consistent with Horizon and supervisor configs. ULID bridge IDs via `Invoice::generateBridgeTransactionId()` (`Str::ulid()`). Retry transmission logic solid with backoff. Weak points: Map/Sign `tries=1`, Horizon missing staging env, job timeout only at Horizon supervisor level (120s). |
| **06** | API & Integration Fragility | EIS client handles sandbox simulation, production HTTP, mTLS optional paths. Webhook pipeline has retries but weak error handling. ~~Critical: vendor API tenant isolation and license/suspension enforcement gaps~~ → **P0 fixes applied June 10, 2026**. Webhook URL config unvalidated. |
| **07** | Code Quality & Stability | **`php artisan test`: 142 passed at analysis time; 150 passed after P0 fixes** (~35s). **`npm run lint`: passed.** No PHP static analysis in CI. Tests cover engine, admin, dedup, sandbox guard, rate limits, vendor tenant isolation, license enforcement; gaps in bulk job edge cases and billing subsystem depth. |
| **08** | Predict Future Failure Points | Hot files: `api/routes/api.php`, `Vendor.php`, `TransactionProcessor.php`, admin React pages. High coupling in invoice pipeline. Billing/licensing high complexity, partial test coverage. Certificate/mTLS production paths under-tested. CI branch mismatch on active RC branch. |
| **09** | Generate Consolidated Risk Report | This document. |

---

## Recommended Fix Priority Order

| Priority | Action | Rationale | Status |
|----------|--------|-----------|--------|
| **P0 — Block go-live** | Fix vendor API tenant isolation (`TransactionController` + `TransactionProcessor`) | Prevents cross-vendor data leakage | **Done** (June 10, 2026) |
| **P0 — Block go-live** | Wire vendor suspension + license enforcement into API middleware/processor | Business/compliance control | **Done** (June 10, 2026) |
| **P1 — Before prod deploy** | Validate non-empty `EIS_ENDPOINT` when not in sandbox mode | Prevents silent transmission failure | Open |
| **P1 — Before prod deploy** | Add Horizon `staging` config; verify sandbox queue workers | Sandbox parity with production | Open |
| **P1 — Before prod deploy** | Enable CI on `release/rc1` / release branches | Catch regressions pre-merge | Open |
| **P2 — Short term** | Refresh `composer.lock`; pin Horizon/Sanctum versions | Reproducible builds | Open |
| **P2 — Short term** | Align alert dedupe config; add webhook URL validation | Operational consistency + SSRF mitigation | Open |
| **P2 — Short term** | Increase Map/Sign job retries; harden webhook connection handling | Pipeline resilience | Open |
| **P3 — Maintenance** | Upgrade Laravel 13.15.0, npm deps (`concurrently`, audit fix) | Security patches | Open |
| **P3 — Maintenance** | Add billing/certificate/mTLS tests; PHP lint in CI | Long-term stability | Open |
| **P3 — Maintenance** | Re-run `composer audit` in CI | PHP CVE visibility | Open |

---

## Verification Commands Executed

| Command | Result |
|---------|--------|
| `php artisan test` (in `api/`, initial analysis) | **142 passed** |
| `php artisan test` (in `api/`, after P0 fixes) | **150 passed** |
| `npm run lint` (in `api/`) | **Passed** |
| `composer outdated --direct` | Laravel patch available; Horizon/Sanctum unbound |
| `composer validate` | Valid with lock drift + wildcard warnings |
| `composer audit` | **Failed** (network timeout) |
| `npm audit` | **2 critical** (dev: `shell-quote` via `concurrently`) |
| PHP 8.3.30 | Missing `pcntl`, `posix`, `redis` extensions locally |

No files were modified during the initial read-only analysis. P0 fixes were applied separately on June 10, 2026.
