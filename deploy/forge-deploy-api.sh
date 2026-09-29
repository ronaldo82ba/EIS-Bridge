#!/usr/bin/env bash
# EIS Bridge - production API deploy script (zero-downtime + monorepo).
# Site: api.eisbridge.com. Paste as the site deploy script (CodeDEV; historical filename).
#
# Web directory: public when the site root is api, or api/public when the site root is the repo.
# Zero-downtime ON.
#
# GET /admin renders resources/views/admin.blade.php, which calls @vite. If
# public/build/manifest.json is missing, Laravel throws
# Illuminate\Foundation\ViteManifestNotFoundException and nginx returns HTTP 500.
# The committed public/build is the fallback when npm is missing or the build fails.

set -euo pipefail

FORGE_PHP_BIN="${FORGE_PHP:-php}"

require_php_redis() {
  if ! "${FORGE_PHP_BIN}" -m 2>/dev/null | grep -qi '^redis$'; then
    echo "ERROR: PHP redis extension (phpredis) is not enabled for ${FORGE_PHP_BIN}."
    echo "Forge -> Server -> PHP -> Extensions (match site PHP version, e.g. 8.5) -> enable redis, then redeploy."
    exit 1
  fi
}

require_redis_server() {
  if ! command -v redis-cli >/dev/null 2>&1; then
    echo "ERROR: redis-cli not found. Install/start Redis on the Forge server."
    exit 1
  fi
  if ! redis-cli ping 2>/dev/null | grep -qE '^PONG'; then
    echo "ERROR: Redis is not responding (redis-cli ping failed)."
    exit 1
  fi
}

validate_eis_endpoint_config() {
  "${FORGE_PHP_BIN}" -r '
    $envPath = $argv[1];
    $env = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
      fwrite(STDERR, "ERROR: Unable to parse .env for EIS endpoint validation.\n");
      exit(1);
    }

    $sandboxRaw = strtolower(trim((string)($env["EIS_SANDBOX_MODE"] ?? "true")));
    $sandboxMode = in_array($sandboxRaw, ["1", "true", "yes", "on"], true);
    if ($sandboxMode) {
      exit(0);
    }

    $endpoint = trim((string)($env["EIS_ENDPOINT"] ?? ""));
    if ($endpoint === "") {
      fwrite(STDERR, "ERROR: EIS_ENDPOINT must be set when EIS_SANDBOX_MODE=false.\n");
      exit(1);
    }

    if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
      fwrite(STDERR, "ERROR: EIS_ENDPOINT must be a valid URL.\n");
      exit(1);
    }

    $parts = parse_url($endpoint);
    $scheme = strtolower((string)($parts["scheme"] ?? ""));
    if ($scheme !== "https") {
      fwrite(STDERR, "ERROR: EIS_ENDPOINT must use HTTPS.\n");
      exit(1);
    }

    $host = strtolower((string)($parts["host"] ?? ""));
    if ($host === "" || $host === "localhost" || str_ends_with($host, ".localhost")) {
      fwrite(STDERR, "ERROR: EIS_ENDPOINT cannot target localhost.\n");
      exit(1);
    }

    $isPublicIp = static function (string $ip): bool {
      return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    };

    $normalizedHost = str_contains($host, ":") ? trim($host, "[]") : $host;
    if (filter_var($normalizedHost, FILTER_VALIDATE_IP)) {
      if (!$isPublicIp($normalizedHost)) {
        fwrite(STDERR, "ERROR: EIS_ENDPOINT cannot target private or reserved IP ranges.\n");
        exit(1);
      }
      exit(0);
    }

    $resolved = @gethostbynamel($normalizedHost);
    if (is_array($resolved)) {
      foreach ($resolved as $ip) {
        if (!$isPublicIp($ip)) {
          fwrite(STDERR, "ERROR: EIS_ENDPOINT resolves to a private or reserved IP.\n");
          exit(1);
        }
      }
    }
  ' "${API_DIR}/.env"
}

# Prefer a fresh Vite build (so VITE_* from the site .env are inlined). If npm
# cannot run, keep the committed public/build so /admin still renders.
refresh_admin_vite_build() {
  local BACKUP
  BACKUP="$(mktemp -d)"
  if [ -d public/build ]; then
    cp -a public/build "${BACKUP}/build"
  fi

  if ! command -v npm >/dev/null 2>&1; then
    echo "WARN: npm not found. Using the committed public/build for the admin SPA."
  elif [ ! -f package-lock.json ]; then
    echo "WARN: package-lock.json missing. Using the committed public/build for the admin SPA."
  elif npm ci --ignore-scripts && npm run build; then
    echo "Vite production build refreshed."
  else
    echo "WARN: npm run build failed. Restoring committed public/build so GET /admin does not throw ViteManifestNotFoundException."
    rm -rf public/build
    if [ -d "${BACKUP}/build" ]; then
      cp -a "${BACKUP}/build" public/build
    fi
  fi
  rm -rf "${BACKUP}"

  local MANIFEST="public/build/manifest.json"
  if [ ! -f "${MANIFEST}" ]; then
    echo "ERROR: ${MANIFEST} is missing."
    echo "GET /admin returns HTTP 500: Illuminate\\Foundation\\ViteManifestNotFoundException (Vite manifest not found)."
    exit 1
  fi

  "${FORGE_PHP_BIN}" -r '
    $manifest = json_decode((string) file_get_contents($argv[1]), true);
    if (!is_array($manifest)) {
      fwrite(STDERR, "ERROR: public/build/manifest.json is not a JSON object.\n");
      exit(1);
    }
    foreach (["resources/css/admin.css", "resources/js/admin/main.jsx"] as $entry) {
      if (!isset($manifest[$entry]["file"])) {
        fwrite(STDERR, "ERROR: Vite manifest is missing {$entry}. GET /admin cannot render the admin SPA.\n");
        exit(1);
      }
    }
    $file = $manifest["resources/js/admin/main.jsx"]["file"];
    fwrite(STDOUT, "Admin Vite manifest OK ({$file}).\n");
  ' "${MANIFEST}"
}

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY"
REPO_ROOT="$FORGE_RELEASE_DIRECTORY"

# Site root may be the repo ("/") or the Laravel app ("api").
if [ -f "${REPO_ROOT}/composer.json" ]; then
  API_DIR="${REPO_ROOT}"
elif [ -f "${REPO_ROOT}/api/composer.json" ]; then
  API_DIR="${REPO_ROOT}/api"
else
  echo "ERROR: composer.json not found in ${REPO_ROOT} or ${REPO_ROOT}/api."
  echo "Set the site root to api (web directory public) or / (web directory api/public)."
  exit 1
fi

if [ -f "${REPO_ROOT}/.env" ] && [ ! -e "${API_DIR}/.env" ]; then
  ln -sf "${REPO_ROOT}/.env" "${API_DIR}/.env"
fi

if [ ! -f "${API_DIR}/.env" ]; then
  echo "ERROR: ${API_DIR}/.env not found. Save the site environment, then redeploy."
  exit 1
fi

cd "${API_DIR}"
echo "Deploying API from ${API_DIR}"

require_php_redis
require_redis_server
validate_eis_endpoint_config

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

refresh_admin_vite_build

${FORGE_PHP_BIN} artisan migrate --force

${FORGE_PHP_BIN} artisan storage:link --force 2>/dev/null || true

${FORGE_PHP_BIN} artisan config:clear
${FORGE_PHP_BIN} artisan config:cache
${FORGE_PHP_BIN} artisan route:cache
${FORGE_PHP_BIN} artisan view:cache
# Boot validated above: route:cache and view:cache boot the full kernel with cached config.

$ACTIVATE_RELEASE()

$RESTART_QUEUES()

echo "EIS Bridge API deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD))"
