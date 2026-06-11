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

if ! BOOT_OUTPUT=$(${FORGE_PHP_BIN} artisan about --only=environment --no-ansi 2>&1); then
  echo "ERROR: Laravel failed to boot after config:cache."
  echo "${BOOT_OUTPUT}"
  echo "See storage/logs/laravel.log in this release for details."
  exit 1
fi

$ACTIVATE_RELEASE()

$RESTART_QUEUES()

echo "EIS Bridge API deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD))"
