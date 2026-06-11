#!/usr/bin/env bash
# EIS Bridge - sandbox API deploy script for Laravel Forge (zero-downtime + monorepo).
# Paste into Forge -> Site -> Deployment -> Deploy Script (sandbox.eisbridge.com).
#
# Works whether Forge root directory is "/" (full monorepo) or "api" (Laravel subfolder).
# Web directory in Forge: api/public OR public (if root is api).

set -euo pipefail

FORGE_PHP_BIN="${FORGE_PHP:-php}"

require_php_redis() {
  if ! "${FORGE_PHP_BIN}" -m 2>/dev/null | grep -qi '^redis$'; then
    echo "ERROR: PHP redis extension (phpredis) is not enabled for ${FORGE_PHP_BIN}."
    echo "Forge -> Server -> PHP 8.3 -> Extensions -> enable redis, then redeploy."
    echo "Sandbox uses SESSION_DRIVER=redis, CACHE_STORE=redis, QUEUE_CONNECTION=redis (see api/.env.sandbox.example)."
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
    echo "Start Redis via Forge -> Server -> Network or: sudo systemctl start redis-server"
    exit 1
  fi
}

assert_cached_app_env() {
  local EXPECTED="$1"
  local CACHED
  CACHED=$("${FORGE_PHP_BIN}" -r "echo (require 'bootstrap/cache/config.php')['app']['env'] ?? 'missing';" 2>/dev/null || echo missing)
  if [ "${CACHED}" != "${EXPECTED}" ]; then
    echo "ERROR: Cached APP_ENV is '${CACHED}', expected '${EXPECTED}' after config:cache."
    echo "Run config:clear && config:cache, or verify Forge Environment and redeploy."
    exit 1
  fi
}

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY"
REPO_ROOT="$FORGE_RELEASE_DIRECTORY"

# Monorepo: composer.json in api/ — or root directory already set to api/
if [ -f "${REPO_ROOT}/composer.json" ]; then
  API_DIR="${REPO_ROOT}"
elif [ -f "${REPO_ROOT}/api/composer.json" ]; then
  API_DIR="${REPO_ROOT}/api"
else
  echo "ERROR: composer.json not found in ${REPO_ROOT} or ${REPO_ROOT}/api"
  echo "Set Forge root directory to api and web directory to public, or root / with web api/public."
  ls -la "${REPO_ROOT}" || true
  exit 1
fi

# Forge shared .env lives at site root; Laravel reads api/.env — always refresh symlink each release.
ENV_LINK_TARGET="${API_DIR}/.env"
SHARED_ENV=""
for CANDIDATE in \
  "${FORGE_SITE_PATH:-}/.env" \
  "${REPO_ROOT}/.env" \
  "${FORGE_SITE_PATH:-}/shared/.env" \
  "$(dirname "${FORGE_SITE_PATH:-/nonexistent}")/.env"; do
  if [ -f "${CANDIDATE}" ]; then
    SHARED_ENV="${CANDIDATE}"
    break
  fi
done

if [ -z "${SHARED_ENV}" ]; then
  echo "ERROR: Forge shared .env not found. Save Environment in Forge first."
  exit 1
fi

ln -sf "${SHARED_ENV}" "${ENV_LINK_TARGET}"

if grep -qE '(^|[[:space:]])APP_ENV[[:space:]]*=[[:space:]]*["'\'']?production["'\'']?' "${ENV_LINK_TARGET}" \
  && grep -qE '(^|[[:space:]])EIS_SANDBOX_MODE[[:space:]]*=[[:space:]]*["'\'']?true["'\'']?' "${ENV_LINK_TARGET}"; then
  echo "ERROR: Sandbox cannot use APP_ENV=production with EIS_SANDBOX_MODE=true."
  echo "Set APP_ENV=staging in Forge Environment (see api/.env.sandbox.example)."
  exit 1
fi

if ! grep -qE '(^|[[:space:]])APP_ENV[[:space:]]*=[[:space:]]*["'\'']?staging["'\'']?' "${ENV_LINK_TARGET}"; then
  echo "ERROR: Sandbox requires APP_ENV=staging (see api/.env.sandbox.example)."
  exit 1
fi

cd "${API_DIR}"
echo "Deploying from API_DIR=${API_DIR}"

require_php_redis
require_redis_server

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f package-lock.json ]; then
  if ! command -v npm >/dev/null 2>&1; then
    echo "ERROR: npm not found on server. Install Node 20 on RonaldoMijaresServer001a."
    exit 1
  fi
  npm ci --ignore-scripts
  npm run build
fi

${FORGE_PHP_BIN} artisan migrate --force

${FORGE_PHP_BIN} artisan storage:link --force 2>/dev/null || true

${FORGE_PHP_BIN} artisan config:clear
${FORGE_PHP_BIN} artisan config:cache
assert_cached_app_env staging
${FORGE_PHP_BIN} artisan route:cache
${FORGE_PHP_BIN} artisan view:cache

if ! ${FORGE_PHP_BIN} artisan route:list --path=up --no-ansi >/dev/null 2>&1; then
  echo "ERROR: Laravel failed to boot after config:cache (see storage/logs/laravel.log)."
  exit 1
fi

$ACTIVATE_RELEASE()

$RESTART_QUEUES()

echo "EIS Bridge sandbox deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo unknown))"
