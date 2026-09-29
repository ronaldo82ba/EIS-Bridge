# Nginx reference — EIS Bridge on Forge

Forge manages live Nginx configs. Use these notes when editing **Site → Nginx Configuration**.

## Laravel API sites (`api.eisbridge.com`, `sandbox.eisbridge.com`)

Forge defaults are usually correct when **Web Directory** is `api/public`.

### Upload size (merchant certificate PFX)

Add inside the `server` block if certificate uploads fail with 413:

```nginx
client_max_body_size 20M;
```

### Horizon dashboard (production)

Restrict `/horizon` to trusted IPs or use Forge's authentication middleware. Example IP allowlist inside `location`:

```nginx
location /horizon {
    allow 203.0.113.10;   # your office IP
    deny all;
    try_files $uri $uri/ /index.php?$query_string;
}
```

Adjust to match Forge's generated PHP location pattern.

## Marketing site (`eisbridge.com`)

Static site — no PHP handler required.

### Security hardening (required)

The marketing deploy script lives in the repo root (`marketing-deploy.sh`) and must **not** be downloadable. Paste the full snippet from [`marketing-security.conf`](marketing-security.conf) into **Forge → Sites → eisbridge.com → Nginx Configuration**, inside the `server { ... }` block, then save and reload Nginx.

The snippet blocks operational scripts (`marketing-deploy.sh`, `connect-forge.ps1`, other `*.sh` / `*.ps1`), internal paths (`/.git`, `/.env`, `/api/`, `/deploy/`, `/scripts/`), and non-public docs under `/docs/`. After applying, verify:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://eisbridge.com/marketing-deploy.sh   # expect 404
curl -sS -o /dev/null -w '%{http_code}\n' https://eisbridge.com/docs/FORGE_DEPLOYMENT.md   # expect 404
```

`marketing-deploy.sh` runs these checks post-deploy and fails if nginx hardening is missing.

### Optional: force www

In Forge **Redirects**, add:

- `eisbridge.com` → `https://www.eisbridge.com` (301)

Or the reverse, depending on your canonical URL preference.

### Serving markdown under `/docs/`

The marketing site links to some `.md` files. Browsers may download rather than render them. Options:

1. Leave as-is (downloads are acceptable for developer docs).
2. Add a simple static file location with correct MIME type.
3. Link to GitHub-rendered docs instead.

No change required for initial launch.