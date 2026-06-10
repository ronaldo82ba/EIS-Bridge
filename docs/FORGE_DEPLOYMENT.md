# Laravel Forge Deployment — EIS Bridge

Step-by-step guide for deploying EIS Bridge to Laravel Forge with **eisbridge.com**. You create sites in the Forge UI; this document covers architecture, DNS, server requirements, and pre-flight checks.

**Repository:** `https://github.com/ronaldo82ba/EIS-Bridge`  
**Local path:** `C:\laragon\www\EIS Bridge`  
**Stack:** Laravel 13 (PHP 8.3) in `api/`, static marketing site at repo root, React admin SPA built with Vite.

---

## Recommended architecture

Use **three Forge sites** on one server (same pattern as your CodeAssist droplet at `forge@188.166.230.4`):

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

Forge injects `FORGE_SITE_PATH`, `FORGE_SITE_BRANCH`, and `FORGE_COMPOSER` at runtime.

**Branch:** `main` or `release/rc1` (current release branch). Enable **Quick Deploy** after the first successful manual deploy.

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

On **each** Laravel site (`api` and `sandbox`), add a Forge **Daemon**:

| Setting | Value |
|---------|-------|
| Command | `php artisan horizon` |
| Directory | `/home/forge/api.eisbridge.com/api` (adjust per site) |
| User | `forge` |
| Processes | 1 |

Reference Supervisor config: [`deploy/supervisor/horizon.conf`](../deploy/supervisor/horizon.conf) (adjust paths to match Forge site paths).

Horizon supervises queues: `mapping`, `signing`, `transmission`, `retry`, `webhooks`.

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
7. **Daemon:** `php artisan horizon` in `.../sandbox.eisbridge.com/api`.
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

# Horizon running
cd /home/forge/api.eisbridge.com/api && php artisan horizon:status

# Schedule registered
php artisan schedule:list
```

Admin console: `https://api.eisbridge.com/admin` (requires seeded admin user — run seeders on first deploy if needed).

---

## Related documentation

- [risk-report-2026-06-10.md](risk-report-2026-06-10.md) — RC1 risk analysis and open deployment items
- [production-eis-setup.md](production-eis-setup.md) — BIR endpoints, certificates, sandbox mode
- [production-ops.md](production-ops.md) — cron, monitoring endpoints
- [queue-workers.md](queue-workers.md) — Horizon and Supervisor
- [security.md](security.md) — rate limits, API keys, Sanctum