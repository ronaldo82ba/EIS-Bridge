#!/usr/bin/env bash
# EIS Bridge - production API deploy script for Laravel Forge (zero-downtime + monorepo).
# Paste into Forge -> Site -> Deployment -> Deploy Script (api.eisbridge.com).
#
# Requires: web directory api/public, zero-downtime ON.

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

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY"
REPO_ROOT="$FORGE_RELEASE_DIRECTORY"
API_DIR="${REPO_ROOT}/api"

if [ -f "${REPO_ROOT}/.env" ] && [ ! -e "${API_DIR}/.env" ]; then
  ln -sf ../.env "${API_DIR}/.env"
fi

if [ ! -f "${API_DIR}/.env" ]; then
  echo "ERROR: ${API_DIR}/.env not found. Create Environment in Forge first."
  exit 1
fi

if [ ! -f "${API_DIR}/composer.json" ]; then
  echo "ERROR: ${API_DIR}/composer.json not found. Web directory must be api/public (monorepo)."
  exit 1
fi

cd "${API_DIR}"

require_php_redis
require_redis_server
validate_eis_endpoint_config

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f package-lock.json ]; then
  npm ci --ignore-scripts
  npm run build
fi

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
