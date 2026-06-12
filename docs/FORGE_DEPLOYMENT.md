# Laravel Forge Deployment — EIS Bridge

Step-by-step guide for deploying EIS Bridge to Laravel Forge with **eisbridge.com**. You create sites in the Forge UI; this document covers architecture, DNS, server requirements, and pre-flight checks.

**Repository:** `https://github.com/ronaldo82ba/EIS-Bridge`  
**Local path:** `C:\laragon\www\EIS Bridge`  
**Stack:** Laravel 13 (PHP 8.3) in `api/`, static marketing site at repo root, React admin SPA built with Vite.

---

## Recommended architecture

Use **three Forge sites** on **RonaldoMijaresServer001a** (`104.248.150.28`):

| Forge site | Domain | Web directory | Project type | Purpose |
|------------|--------|---------------|--------------|---------|
| `eisbridge.com` | `eisbridge.com`, `www.eisbridge.com` | `/` (repo root) | Static HTML | Marketing landing + `portal/` developer docs |
| `api.eisbridge.com` | `api.eisbridge.com` | `/api/public` | Laravel | Production Vendor API (`/v1/*`), admin console (`/admin`, `/api/admin/*`), Horizon |
| `sandbox.eisbridge.com` | `sandbox.eisbridge.com` | `/api/public` | Laravel | Vendor onboarding sandbox — **separate `.env` and database**, `EIS_SANDBOX_MODE=true` |

### Why not one Laravel site for everything?

The repo is a **monorepo**: static HTML lives at the root (`index.html`, `portal/`, `styles/`), while Laravel lives in `api/` with `public/` as the web root. A single Laravel site cannot serve the marketing homepage without custom Nginx aliases. Separate sites keep deploys simple and match how Forge expects Laravel (`api/public`) vs static content.

### Why separate sandbox and production API?

The app **refuses to boot** when `APP_ENV=production` and `EIS_SANDBOX_MODE=true`. Sandbox vendors need simulated BIR transmission; production needs real endpoints and merchant certificates. Separate Forge sites = separate `.env`, databases, and queue workers without hostname-based hacks.

---

## Server requirements

Provision or reuse a **4 GB+ droplet** (your $32/mo tier is appropriate). On the Forge server, install/enable:

| Component | Version / notes |
|-----------|-----------------|
| **PHP** | **8.3** (matches `api/composer.json`) |
| **PHP extensions** | `openssl` (JWS signing), `redis`, `pdo_mysql` (or `pdo_pgsql`), `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `fileinfo`, `intl` |
| **Composer** | Latest (Forge installs by default) |
| **Node.js** | **20 LTS** — required on API/sandbox sites for `npm run build` (admin SPA) |
| **MySQL 8** or **PostgreSQL 15+** | Two databases: `eis_bridge_production`, `eis_bridge_sandbox` |
| **Redis** | Required for `QUEUE_CONNECTION=redis`, sessions, cache |
| **Supervisor** | Managed by Forge Daemons for Horizon |

### Forge server recipe (before creating sites)

1. **Server → Meta → PHP** — set default to **8.3**.
2. **Server → Databases** — create both MySQL databases and note credentials.
3. **Server → Network / Redis** — ensure Redis is running (Forge one-click or `redis-server`).
4. Confirm **Scheduler** is enabled at the server level (Forge adds the cron entry).

### Local SSH access (Windows)

From the repo root on your machine, use the helper script to open an SSH session to **RonaldoMijaresServer001a** (`104.248.150.28`). First connect accepts the host key automatically; you can connect without a key initially or generate one and add it in Forge.

```powershell
# Interactive session (auto-detects ~/.ssh/id_ed25519 or id_rsa if present)
.\scripts\connect-forge.ps1

# Sandbox site path hint
.\scripts\connect-forge.ps1 -Site sandbox

# Generate a key and show Forge paste instructions
.\scripts\connect-forge.ps1 -SetupKey

# After adding the public key in Forge → Server → SSH Keys
.\scripts\connect-forge.ps1 -KeyPath "$env:USERPROFILE\.ssh\id_ed25519"
```

See [`scripts/connect-forge.ps1`](../scripts/connect-forge.ps1) for parameters (`-Host`, `-User`, `-KeyPath`, `-Site`).

---

## DNS records (eisbridge.com)

Point records at your Forge server public IP (replace `YOUR_FORGE_IP`):

| Type | Host / name | Value | TTL | Notes |
|------|-------------|-------|-----|-------|
| **A** | `@` | `YOUR_FORGE_IP` | 300 | Apex → marketing site |
| **A** or **CNAME** | `www` | `YOUR_FORGE_IP` or `eisbridge.com` | 300 | Marketing alias |
| **A** | `api` | `YOUR_FORGE_IP` | 300 | Production API |
| **A** | `sandbox` | `YOUR_FORGE_IP` | 300 | Sandbox API |

**SSL:** After DNS propagates, use **Forge → Site → SSL → Lets Encrypt** on each site. Enable HTTPS and force SSL.

**Email (optional):** Add MX/SPF at your registrar if you send mail from `@eisbridge.com`; not required for API deployment.

---

## Deploy scripts

Paste the contents of these files into **Forge → Site → Deployment → Deploy Script**:

| Site | Script file |
|------|-------------|
| `eisbridge.com` | [`marketing-deploy.sh`](../marketing-deploy.sh) |
| `api.eisbridge.com` | [`deploy/forge-deploy-api.sh`](../deploy/forge-deploy-api.sh) |
| `sandbox.eisbridge.com` | [`deploy/forge-forge-ui-sandbox.sh`](../deploy/forge-forge-ui-sandbox.sh) |

Forge injects `$FORGE_RELEASE_DIRECTORY`, `$FORGE_COMPOSER`, and `$FORGE_PHP` at runtime on new sites (zero-downtime enabled by default).

**Branch:** `main` or `release/rc1` (current release branch). Enable **Quick Deploy** after the first successful manual deploy.

### Monorepo + zero-downtime (API sites) — required

EIS Bridge is a **monorepo**: `composer.json` lives in **`api/`**, not the repo root. Forge’s default Laravel flow runs `composer install` at the release root and fails with *“does not contain a composer.json file”*.

**Site settings (Settings → General → Advanced):**

| Field | Value |
|-------|-------|
| **Root directory** | **`api`** |
| **Web directory** | **`public`** |

**Also required:**

1. **Uncheck “Install Composer dependencies”** when connecting the repo (or in site Git/repository settings). Composer runs inside the deploy script under `api/` instead.
2. **Deploy script** must use zero-downtime macros (`$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()`, `$RESTART_QUEUES()`) and `cd` into `api/` before `$FORGE_COMPOSER install` — see [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh).
3. **Shared paths** (Settings → Deployments): add **`api/storage`** so uploads persist across releases.
4. **Environment** must be saved before deploy; deploy script symlinks release-root `.env` → `api/.env`.

If the build step still fails, confirm root directory is **`api`**, not `/`.

---

## Environment variables

Templates:

- Production API: [`api/.env.production.example`](../api/.env.production.example)
- Sandbox API: [`api/.env.sandbox.example`](../api/.env.sandbox.example)
- Local dev reference: [`api/.env.example`](../api/.env.example)

### Critical production values

| Variable | Production (`api`) | Sandbox |
|----------|-------------------|---------|
| `APP_ENV` | `production` | `staging` |
| `APP_URL` | `https://api.eisbridge.com` | `https://sandbox.eisbridge.com` |
| `EIS_SANDBOX_MODE` | **`false`** | **`true`** |
| `EIS_ENDPOINT` | **required**, HTTPS public endpoint only | sandbox endpoint |
| `SANDBOX_API_KEY` | empty (unused) | **required** — shared gate; send as `X-SANDBOX-API-KEY` on `/v1/*` |
| `QUEUE_CONNECTION` | `redis` | `redis` |
| `DB_DATABASE` | `eis_bridge_production` | `eis_bridge_sandbox` |

Generate `APP_KEY` once per site:

```bash
cd /home/forge/api.eisbridge.com/current/api
php artisan key:generate --show
```

Paste the output into Forge → Site → Environment.

Generate a sandbox gate key (once per sandbox site):

```bash
openssl rand -hex 32
```

Set `SANDBOX_API_KEY` to that value in Forge → Site → Environment. Vendor API clients must send `X-SANDBOX-API-KEY: <value>` on every `/v1/*` request (in addition to `Authorization: Bearer` for vendor routes).

---

## Queue workers and scheduler

### Horizon (API + sandbox sites)

Add a Forge **Daemon** on **each** Laravel site **after the first successful deploy** (Horizon fatals if `vendor/`, `api/.env`, or Redis are missing).

#### Sandbox (`sandbox.eisbridge.com`) — Forge Daemons UI

| Field | Value |
|-------|-------|
| **Command** | `php8.5 artisan horizon` |
| **Directory** | `/home/forge/sandbox.eisbridge.com/current/api` |
| **User** | `forge` |
| **Processes** | `1` |

#### Production API (`api.eisbridge.com`) — Forge Daemons UI

| Field | Value |
|-------|-------|
| **Command** | `php8.5 artisan horizon` |
| **Directory** | `/home/forge/api.eisbridge.com/current/api` |
| **User** | `forge` |
| **Processes** | `1` |

Use the **site PHP binary** (e.g. **`php8.5`** on RonaldoServer06102026001a — check **Site → Meta → PHP Version**; not bare `php`) so the daemon matches FPM. Directory must be the Laravel root (`api/`), **without** a trailing slash. Do not point the daemon at the repo root or `api/public`.

#### Supervisor block (sandbox reference)

Forge generates a file like `/etc/supervisor/conf.d/daemon-876317.conf`. It defaults **`stopwaitsecs=15`**, which is too short for Horizon — Laravel recommends **`3600`** so workers can finish in-flight jobs on restart/deploy. After creating the daemon in Forge, SSH to the server, edit the generated file, set `stopwaitsecs=3600`, then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart daemon-876317:*
```

Corrected sandbox block (replace `876317` with your Forge daemon ID):

```ini
[program:daemon-876317]
directory=/home/forge/sandbox.eisbridge.com/current/api
command=php8.5 artisan horizon
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/home/forge/.forge/daemon-876317.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
stopwaitsecs=3600
stopasgroup=true
killasgroup=true
```

Reference copy in repo: [`deploy/supervisor/horizon.conf`](../deploy/supervisor/horizon.conf).

Horizon supervises queues: `mapping`, `signing`, `transmission`, `retry`, `webhooks`, `default`. Sandbox uses `APP_ENV=staging`; see `staging` in [`api/config/horizon.php`](../api/config/horizon.php).

**Deploy restarts:** [`deploy/forge-forge-ui-sandbox.sh`](../deploy/forge-forge-ui-sandbox.sh) calls Forge `$RESTART_QUEUES()` after `$ACTIVATE_RELEASE()`. The deploy body also runs `php artisan horizon:terminate` before activation so in-flight jobs can finish gracefully.

**Health check:** `GET https://sandbox.eisbridge.com/horizon-health` returns JSON `{"status":"running"|"stopped",...}` (HTTP 200 when running, 503 when stopped). Does not require `X-SANDBOX-API-KEY`. (Avoid `/horizon/health` — Horizon's dashboard SPA registers a catch-all at `/horizon/{view?}`.)

#### Prerequisites (avoid Horizon FATAL on start)

1. **Deploy first** — run **Deploy Now** so `composer install` creates `vendor/` and migrations run.
2. **`api/.env` must exist** — Forge → Site → Environment writes the file. For this monorepo the deploy scripts expect it at `{site}/api/.env`. If Forge wrote `.env` only at the site root, symlink before starting Horizon:

   ```bash
   ln -sf /home/forge/sandbox.eisbridge.com/.env /home/forge/sandbox.eisbridge.com/current/api/.env
   ```

3. **`APP_KEY` set** — generate with `php artisan key:generate --show` in `api/`.
4. **Redis running** — `QUEUE_CONNECTION=redis` and `REDIS_HOST=127.0.0.1` (see `.env.sandbox.example`).
5. **Manual smoke test** before enabling the daemon:

   ```bash
   cd /home/forge/sandbox.eisbridge.com/current/api
   php8.5 artisan horizon:status
   php8.5 artisan horizon   # Ctrl+C after "Horizon started successfully"
   ```

   Check Forge daemon log on failure: `/home/forge/.forge/daemon-*.log`.

### Scheduler

Enable the Forge **Scheduler** toggle on each Laravel API site (**Site → Scheduler → Enable**). Forge adds a cron entry that runs `php artisan schedule:run` every minute.

Scheduled tasks (see [`api/routes/console.php`](../api/routes/console.php)):

- `observability:check` — every 10 minutes
- `licenses:check-renewals` — daily
- `certificates:scan-expiry` — daily at 01:00
- `queues:broadcast` — every 30 seconds

All scheduled commands append output to `storage/logs/scheduler.log` on the server.

Verify after deploy:

```bash
cd /home/forge/sandbox.eisbridge.com/current/api && php8.5 artisan schedule:list
tail -20 storage/logs/scheduler.log
```

See also [production-ops.md](production-ops.md) and [queue-workers.md](queue-workers.md).

---

## Nginx notes

Forge generates Nginx configs automatically. Adjust only if needed via **Site → Nginx Configuration**.

### Marketing site (`eisbridge.com`)

Use the hardened snippet at [`deploy/nginx/marketing-security.conf`](../deploy/nginx/marketing-security.conf) to prevent source/config exposure on the static site.

**Apply in Forge (required):**

1. Open **Forge → Sites → eisbridge.com → Nginx Configuration**.
2. Paste the full contents of `deploy/nginx/marketing-security.conf` inside the `server { ... }` block.
3. Save and reload Nginx when Forge prompts.
4. Verify blocked paths return `404` (not `500`) and public docs still resolve.

**Key protections in the snippet:**

- Blocks direct access to `/.git`, `/.env`, `*.sh`, `/marketing-deploy.sh`, `/api/`, `/deploy/`, `/scripts/`, `README*`, `RELEASE_NOTES*`, and common sensitive extensions.
- Limits `/docs/` to the public allowlist only:
  - `/docs/partner-program.md`
  - `/docs/certification-playbook.md`
  - `/docs/vendor-api.md`
  - `/docs/qa/integration-test-cases-v1.md`
  - `/docs/postman/EIS-Bridge-API-v1.postman_collection.json`
  - `/docs/schemas/sale-object.schema.json`
- Adds baseline security headers (HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CSP) suitable for static pages.

Optional: redirect apex ↔ www in Forge **Redirects** to enforce a canonical host.

### Laravel API sites

| Setting | Value |
|---------|-------|
| **Web directory** | `api/public` |
| **PHP version** | 8.3 |

Vendor API base URL: `https://api.eisbridge.com/v1`  
Health check: `https://api.eisbridge.com/up`  
Horizon dashboard: `https://api.eisbridge.com/horizon` (restrict access in production — IP allowlist or Forge authentication).

Example optional upload limit for certificate uploads (`api` site Nginx):

```nginx
client_max_body_size 20M;
```

Reference snippets: [`deploy/nginx/README.md`](../deploy/nginx/README.md).

---

## Step-by-step: Create Site in Forge

Repeat for each of the three sites. Order: **marketing → sandbox API → production API** (sandbox first lets you test without production secrets).

### A. Connect Git (once per server)

1. **Forge → Server → SSH Keys** — ensure your GitHub deploy key or account is linked.
2. Confirm the server can clone `ronaldo82ba/EIS-Bridge`.

### B. Marketing site — `eisbridge.com`

1. **Sites → New Site** → domain `eisbridge.com`, project type **Static HTML** (or PHP if Static is unavailable — no PHP execution needed).
2. **Web directory:** leave as `/` (site root = repo root).
3. **Repository:** `ronaldo82ba/EIS-Bridge`, branch `release/rc1`.
4. **Deploy script:** paste `marketing-deploy.sh` and set Forge to run `bash marketing-deploy.sh` (defaults to `release/rc1`, verifies `index.html`, `portal/`, `styles/`, and `marketing-deploy.sh` before marking deploy successful).
5. **SSL:** add `eisbridge.com` and `www.eisbridge.com`, obtain Lets Encrypt certificate.
6. **Deploy Now** — verify `https://eisbridge.com` and `https://eisbridge.com/portal/`.

### C. Sandbox API — `sandbox.eisbridge.com`

1. **New Site** → `sandbox.eisbridge.com`, type **PHP / Laravel**.
2. **Web directory:** `api/public`.
3. **PHP:** 8.3.
4. **Database:** link `eis_bridge_sandbox` (Forge → Site → Database).
5. **Environment:** paste from `api/.env.sandbox.example`, set `DB_*`, generate `APP_KEY`.
6. **Deploy script:** paste [`deploy/forge-forge-ui-sandbox.sh`](../deploy/forge-forge-ui-sandbox.sh) (not `forge-deploy-sandbox.sh` — the UI wrapper runs zero-downtime macros and `$RESTART_QUEUES()`).
7. **Deploy Now** first, then **Daemon:** `php8.5 artisan horizon`, directory `/home/forge/sandbox.eisbridge.com/current/api` (see [Horizon](#horizon-api--sandbox-sites) for `stopwaitsecs` and prerequisites).
8. **Scheduler:** **Site → Scheduler → Enable**.
9. **Environment:** set `SANDBOX_API_KEY` (generate with `openssl rand -hex 32`), **Save**, then redeploy.
10. **SSL** → Deploy Now.
11. Smoke test — see [Post-deploy smoke tests (sandbox)](#post-deploy-smoke-tests-sandbox) below.

### D. Production API — `api.eisbridge.com`

Same as sandbox, but:

- Database: `eis_bridge_production`
- Environment: `api/.env.production.example`
- **`EIS_SANDBOX_MODE=false`** and set `EIS_ENDPOINT` from BIR registration before go-live
- Configure Pusher (or set `BROADCAST_CONNECTION=log` initially)
- Set `ALERTS_ADMIN_EMAIL` and SMTP mail settings

---

## Pre-flight checklist

Before clicking **Create Site** / **Deploy Now**:

- [ ] Forge server running PHP 8.3, Redis, MySQL, Node 20
- [ ] GitHub repo `ronaldo82ba/EIS-Bridge` accessible from server
- [ ] DNS A records for `@`, `www`, `api`, `sandbox` → server IP
- [ ] Two databases created with distinct names
- [ ] `.env` drafted from the correct example file per site
- [ ] `APP_KEY` generated for each Laravel site
- [ ] Deploy script pasted from `deploy/forge-deploy-*.sh`
- [ ] Web directory set to `api/public` for Laravel sites
- [ ] Horizon daemon configured on both API sites
- [ ] Scheduler enabled on both API sites
- [ ] SSL planned (Lets Encrypt after DNS propagation)
- [ ] Production: `EIS_SANDBOX_MODE=false` confirmed
- [ ] Sandbox: `EIS_SANDBOX_MODE=true` confirmed
- [ ] BIR production endpoint and mTLS cert paths ready before production go-live (see [production-eis-setup.md](production-eis-setup.md))

---

## Post-deploy verification

```bash
# Health
curl -sS https://api.eisbridge.com/up
curl -sS https://sandbox.eisbridge.com/up

# Migrations applied
cd /home/forge/api.eisbridge.com/current/api && php artisan migrate:status

# Horizon running (repeat for sandbox path)
cd /home/forge/sandbox.eisbridge.com/current/api && php8.5 artisan horizon:status
cd /home/forge/api.eisbridge.com/current/api && php8.5 artisan horizon:status

# Schedule registered
php8.5 artisan schedule:list
```

Admin console: `https://api.eisbridge.com/admin` (requires seeded admin user — run seeders on first deploy if needed).

---

## Post-deploy smoke tests (sandbox)

Run from your workstation after DNS, SSL, deploy, Horizon daemon, Scheduler, and `SANDBOX_API_KEY` are configured on **sandbox.eisbridge.com**.

Replace `YOUR_SANDBOX_API_KEY` with the value from Forge → Site → Environment.

```bash
# 1. Laravel health (no sandbox gate)
curl -sS -o /dev/null -w "HTTP %{http_code}\n" https://sandbox.eisbridge.com/up
# Expected: HTTP 200

# 2. Vendor API health — requires X-SANDBOX-API-KEY (routes are /v1/*, not /api/v1/*)
curl -sS -H "X-SANDBOX-API-KEY: YOUR_SANDBOX_API_KEY" \
  https://sandbox.eisbridge.com/v1/health
# Expected: JSON with "status":"healthy" (or warning if Redis/queue not ready)

# 3. Sandbox gate rejects missing header
curl -sS -o /dev/null -w "HTTP %{http_code}\n" https://sandbox.eisbridge.com/v1/health
# Expected: HTTP 401

# 4. Horizon worker health (no sandbox gate)
curl -sS https://sandbox.eisbridge.com/horizon-health
# Expected: HTTP 200 with "status":"running" when daemon is up; HTTP 503 if Horizon stopped
```

On the server:

```bash
cd /home/forge/sandbox.eisbridge.com/current/api
php8.5 artisan horizon:status
tail -20 storage/logs/scheduler.log
grep SANDBOX_API_KEY .env   # confirm key is set (do not paste value in tickets)
```

---

## Sandbox Forge UI activation (exact steps)

Complete these in the Forge UI for **sandbox.eisbridge.com** after the first deploy succeeds:

| Step | Forge path | Action |
|------|------------|--------|
| 1. Deploy script | **Site → Deployment → Deploy Script** | Paste entire [`deploy/forge-forge-ui-sandbox.sh`](../deploy/forge-forge-ui-sandbox.sh), **Save** |
| 2. Environment | **Site → Environment** | Paste from [`api/.env.sandbox.example`](../api/.env.sandbox.example); set `APP_KEY`, `DB_*`, `SANDBOX_API_KEY` (`openssl rand -hex 32`), **Save** |
| 3. Redeploy | **Site → Deployment → Deploy Now** | Wait for green; confirm log shows `assert_valid_app_key` pass and `horizon:terminate` |
| 4. Horizon daemon | **Site → Daemons → Create Daemon** | Command: `php8.5 artisan horizon` (match site PHP version); Directory: `/home/forge/sandbox.eisbridge.com/current/api`; User: `forge`; Processes: `1` |
| 5. Supervisor tuning | SSH (see [Horizon](#horizon-api--sandbox-sites)) | Set `stopwaitsecs=3600` in `/etc/supervisor/conf.d/daemon-*.conf`, then `sudo supervisorctl reread && sudo supervisorctl update` |
| 6. Scheduler | **Site → Scheduler → Enable** | Toggle on; verify with `php8.5 artisan schedule:list` over SSH |
| 7. SSL | **Site → SSL → Lets Encrypt** | Obtain certificate, force HTTPS |
| 8. Verify | Workstation | Run [Post-deploy smoke tests (sandbox)](#post-deploy-smoke-tests-sandbox) |

To rotate `SANDBOX_API_KEY`: update **Site → Environment**, **Save**, **Deploy Now**. Distribute the new value to sandbox vendors.

---

## Troubleshooting

### HTTP 500 on `/up` or `/` (empty body)

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Deploy OK, `/up` returns **500** with empty body | Stale `bootstrap/cache/config.php` still has `APP_ENV=production` while Forge `.env` was corrected to `staging` | Redeploy with latest [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh) (runs `config:clear` then `config:cache`). Or SSH: `cd api && php artisan config:clear && php artisan config:cache` |
| Deploy fails: **Laravel failed to boot after config:cache** | Stale Forge deploy script still runs a post-cache `artisan about` smoke test (removed in `release/rc1`) | Re-paste latest [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh) from `release/rc1` and redeploy. If `route:cache` and `view:cache` succeeded, boot is already OK. |
| Deploy fails: **PHP redis extension not enabled** | `SESSION_DRIVER=redis` + `REDIS_CLIENT=phpredis` but phpredis missing | **Forge → Server → PHP → Extensions** (same version as site, e.g. **PHP 8.5**) **→ enable `redis`** → **Restart PHP** → redeploy |
| Deploy fails: **redis-cli ping failed** | Redis service not running | **Forge → Server → Network** (install Redis) or `sudo systemctl start redis-server` |
| `/up` returns **500** JSON `{"status":"down"}` | `APP_ENV=production` with `EIS_SANDBOX_MODE=true` (intentional guard) | Set `APP_ENV=staging` in Forge Environment for sandbox; save and redeploy |
| `/` returns 500 but `/up` is 200 | Admin Vite build missing or session/redis error on web routes | Confirm `npm run build` in deploy log; check `storage/logs/laravel.log` |
| `/up` returns **500** with `Unsupported cipher or incorrect key length` | `APP_KEY` in Forge Environment is wrong length (e.g. truncated, extra characters, or not `base64:` format) — must decode to **32 bytes** for AES-256-CBC | Locally: `cd api && php artisan key:generate --show`. Copy the full `base64:...` value into **Forge → Site → Environment**, **Save**, redeploy. Latest [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh) fails deploy early with byte count if `APP_KEY` is invalid |
| Deploy fails: **APP_KEY decodes to N bytes, expected 32** | Same as above — caught before `config:cache` | Regenerate with `php artisan key:generate --show`; replace entire `APP_KEY` line in Forge Environment (do not hand-edit the base64 payload) |

**Quick checks on the server** (after SSH):

```bash
cd /home/forge/sandbox.eisbridge.com/current/api   # or current release path
php8.5 -m | grep -i redis                  # must print "redis"
redis-cli ping                             # must print PONG
grep APP_ENV .env                          # sandbox: staging
php8.5 artisan about --only=environment    # Environment -> staging
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1/up   # via site nginx if needed
tail -50 storage/logs/laravel.log
```

**Git warning during deploy:** `— is not a valid attribute name: .gitattributes:1` — caused by a UTF-8 BOM and em-dash on line 1 of `.gitattributes` in older commits. Fixed in repo; pull latest `release/rc1` to clear the warning.

---

## Production next steps (`api.eisbridge.com`)

1. **Site** — PHP/Laravel on `api.eisbridge.com`, root directory `api`, web directory `public`, PHP **8.3**, branch `main` or `release/rc1`.
2. **Database** — link `eis_bridge_production` (distinct from sandbox).
3. **Environment** — paste [`api/.env.production.example`](../api/.env.production.example); set `APP_ENV=production`, `APP_URL=https://api.eisbridge.com`, **`EIS_SANDBOX_MODE=false`**, Redis, SMTP, `ALERTS_ADMIN_EMAIL`, Pusher (or `BROADCAST_CONNECTION=log` initially).
4. **BIR** — configure production `EIS_ENDPOINT` and merchant mTLS paths per [production-eis-setup.md](production-eis-setup.md) before accepting live traffic.
5. **Deploy** — paste [`deploy/forge-deploy-api.sh`](../deploy/forge-deploy-api.sh); uncheck Forge “Install Composer dependencies”; add shared path `api/storage`.
6. **Deploy Now** — confirm migrations and `npm run build` succeed.
7. **Horizon daemon** — command `php8.5 artisan horizon`, directory `/home/forge/api.eisbridge.com/current/api`, user `forge`, processes `1`; set `stopwaitsecs=3600` in Supervisor after create.
8. **Scheduler** — enable on the site.
9. **SSL** — Lets Encrypt for `api.eisbridge.com`, force HTTPS.
10. **Verify** — `curl -sS https://api.eisbridge.com/up` → 200; `php8.5 artisan horizon:status`; restrict `/horizon` (IP allowlist or Forge auth).

---

## Related documentation

- [risk-report-2026-06-10.md](risk-report-2026-06-10.md) — RC1 risk analysis and open deployment items
- [production-eis-setup.md](production-eis-setup.md) — BIR endpoints, certificates, sandbox mode
- [production-ops.md](production-ops.md) — cron, monitoring endpoints
- [queue-workers.md](queue-workers.md) — Horizon and Supervisor
- [security.md](security.md) — rate limits, API keys, Sanctum
