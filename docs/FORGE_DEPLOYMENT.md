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
| `eisbridge.com` | [`deploy/forge-deploy-marketing.sh`](../deploy/forge-deploy-marketing.sh) |
| `api.eisbridge.com` | [`deploy/forge-deploy-api.sh`](../deploy/forge-deploy-api.sh) |
| `sandbox.eisbridge.com` | [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh) |

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
| `QUEUE_CONNECTION` | `redis` | `redis` |
| `DB_DATABASE` | `eis_bridge_production` | `eis_bridge_sandbox` |

Generate `APP_KEY` once per site:

```bash
cd /home/forge/api.eisbridge.com/api
php artisan key:generate --show
```

Paste the output into Forge → Site → Environment.

---

## Queue workers and scheduler

### Horizon (API + sandbox sites)

Add a Forge **Daemon** on **each** Laravel site **after the first successful deploy** (Horizon fatals if `vendor/`, `api/.env`, or Redis are missing).

#### Sandbox (`sandbox.eisbridge.com`) — Forge Daemons UI

| Field | Value |
|-------|-------|
| **Command** | `php8.3 artisan horizon` |
| **Directory** | `/home/forge/sandbox.eisbridge.com/api` |
| **User** | `forge` |
| **Processes** | `1` |

#### Production API (`api.eisbridge.com`) — Forge Daemons UI

| Field | Value |
|-------|-------|
| **Command** | `php8.3 artisan horizon` |
| **Directory** | `/home/forge/api.eisbridge.com/api` |
| **User** | `forge` |
| **Processes** | `1` |

Use **`php8.3`** (not bare `php`) so the daemon matches the site PHP version. Directory must be the Laravel root (`api/`), **without** a trailing slash. Do not point the daemon at the repo root or `api/public`.

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
directory=/home/forge/sandbox.eisbridge.com/api
command=php8.3 artisan horizon
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

#### Prerequisites (avoid Horizon FATAL on start)

1. **Deploy first** — run **Deploy Now** so `composer install` creates `vendor/` and migrations run.
2. **`api/.env` must exist** — Forge → Site → Environment writes the file. For this monorepo the deploy scripts expect it at `{site}/api/.env`. If Forge wrote `.env` only at the site root, symlink before starting Horizon:

   ```bash
   ln -sf /home/forge/sandbox.eisbridge.com/.env /home/forge/sandbox.eisbridge.com/api/.env
   ```

3. **`APP_KEY` set** — generate with `php artisan key:generate --show` in `api/`.
4. **Redis running** — `QUEUE_CONNECTION=redis` and `REDIS_HOST=127.0.0.1` (see `.env.sandbox.example`).
5. **Manual smoke test** before enabling the daemon:

   ```bash
   cd /home/forge/sandbox.eisbridge.com/api
   php8.3 artisan horizon:status
   php8.3 artisan horizon   # Ctrl+C after "Horizon started successfully"
   ```

   Check Forge daemon log on failure: `/home/forge/.forge/daemon-*.log`.

### Scheduler

Forge enables `* * * * * php artisan schedule:run` when you toggle **Scheduler** on the site. Required scheduled tasks:

- `observability:check` — every 10 minutes
- `licenses:check-renewals` — daily
- `certificates:scan-expiry` — daily at 01:00
- `queues:broadcast` — every 30 seconds

Verify after deploy:

```bash
cd api && php artisan schedule:list
```

See also [production-ops.md](production-ops.md) and [queue-workers.md](queue-workers.md).

---

## Nginx notes

Forge generates Nginx configs automatically. Adjust only if needed via **Site → Nginx Configuration**.

### Marketing site (`eisbridge.com`)

Default static site config is sufficient. Optional: redirect apex ↔ www in Forge **Redirects**.

Ensure `portal/` and `docs/` are reachable (no PHP blocking). Static `.md` links in `index.html` may 404 unless you add a location block or convert to HTML — verify after deploy.

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
3. **Repository:** `ronaldo82ba/EIS-Bridge`, branch `main` or `release/rc1`.
4. **Deploy script:** paste `deploy/forge-deploy-marketing.sh`.
5. **SSL:** add `eisbridge.com` and `www.eisbridge.com`, obtain Lets Encrypt certificate.
6. **Deploy Now** — verify `https://eisbridge.com` and `https://eisbridge.com/portal/`.

### C. Sandbox API — `sandbox.eisbridge.com`

1. **New Site** → `sandbox.eisbridge.com`, type **PHP / Laravel**.
2. **Web directory:** `api/public`.
3. **PHP:** 8.3.
4. **Database:** link `eis_bridge_sandbox` (Forge → Site → Database).
5. **Environment:** paste from `api/.env.sandbox.example`, set `DB_*`, generate `APP_KEY`.
6. **Deploy script:** paste `deploy/forge-deploy-sandbox.sh`.
7. **Deploy Now** first, then **Daemon:** `php8.3 artisan horizon`, directory `/home/forge/sandbox.eisbridge.com/api` (see [Horizon](#horizon-api--sandbox-sites) for `stopwaitsecs` and prerequisites).
8. **Scheduler:** enable.
9. **SSL** → Deploy Now.
10. Smoke test: `GET https://sandbox.eisbridge.com/up` → 200.

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
cd /home/forge/api.eisbridge.com/api && php artisan migrate:status

# Horizon running (repeat for sandbox path)
cd /home/forge/sandbox.eisbridge.com/api && php8.3 artisan horizon:status
cd /home/forge/api.eisbridge.com/api && php8.3 artisan horizon:status

# Schedule registered
php artisan schedule:list
```

Admin console: `https://api.eisbridge.com/admin` (requires seeded admin user — run seeders on first deploy if needed).

---

## Troubleshooting

### HTTP 500 on `/up` or `/` (empty body)

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Deploy OK, `/up` returns **500** with empty body | Stale `bootstrap/cache/config.php` still has `APP_ENV=production` while Forge `.env` was corrected to `staging` | Redeploy with latest [`deploy/forge-deploy-sandbox.sh`](../deploy/forge-deploy-sandbox.sh) (runs `config:clear` then `config:cache`). Or SSH: `cd api && php artisan config:clear && php artisan config:cache` |
| Deploy fails: **PHP redis extension not enabled** | `SESSION_DRIVER=redis` + `REDIS_CLIENT=phpredis` but phpredis missing | **Forge → Server → PHP 8.3 → Extensions → enable `redis`** → redeploy |
| Deploy fails: **redis-cli ping failed** | Redis service not running | **Forge → Server → Network** (install Redis) or `sudo systemctl start redis-server` |
| `/up` returns **500** JSON `{"status":"down"}` | `APP_ENV=production` with `EIS_SANDBOX_MODE=true` (intentional guard) | Set `APP_ENV=staging` in Forge Environment for sandbox; save and redeploy |
| `/` returns 500 but `/up` is 200 | Admin Vite build missing or session/redis error on web routes | Confirm `npm run build` in deploy log; check `storage/logs/laravel.log` |

**Quick checks on the server** (after SSH):

```bash
cd /home/forge/sandbox.eisbridge.com/api   # or current release path
php8.3 -m | grep -i redis                  # must print "redis"
redis-cli ping                             # must print PONG
grep APP_ENV .env                          # sandbox: staging
php8.3 artisan about --only=environment    # Environment -> staging
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
7. **Horizon daemon** — command `php8.3 artisan horizon`, directory `/home/forge/api.eisbridge.com/api`, user `forge`, processes `1`; set `stopwaitsecs=3600` in Supervisor after create.
8. **Scheduler** — enable on the site.
9. **SSL** — Lets Encrypt for `api.eisbridge.com`, force HTTPS.
10. **Verify** — `curl -sS https://api.eisbridge.com/up` → 200; `php8.3 artisan horizon:status`; restrict `/horizon` (IP allowlist or Forge auth).

---

## Related documentation

- [risk-report-2026-06-10.md](risk-report-2026-06-10.md) — RC1 risk analysis and open deployment items
- [production-eis-setup.md](production-eis-setup.md) — BIR endpoints, certificates, sandbox mode
- [production-ops.md](production-ops.md) — cron, monitoring endpoints
- [queue-workers.md](queue-workers.md) — Horizon and Supervisor
- [security.md](security.md) — rate limits, API keys, Sanctum