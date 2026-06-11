#!/usr/bin/env bash
# EIS Bridge - sandbox API deploy body (run from git via deploy/forge-forge-ui-sandbox.sh).
# Do NOT paste this file into Forge — paste deploy/forge-forge-ui-sandbox.sh instead.
#
# Works whether Forge root directory is "/" (full monorepo) or "api" (Laravel subfolder).
# Web directory in Forge: api/public OR public (if root is api).

set -euo pipefail

FORGE_PHP_BIN="${FORGE_PHP:-php}"

require_php_redis() {
  if ! "${FORGE_PHP_BIN}" -m 2>/dev/null | grep -qi '^redis$'; then
    echo "ERROR: PHP redis extension (phpredis) is not enabled for ${FORGE_PHP_BIN}."
    echo "Forge -> Server -> PHP -> Extensions (match site PHP version, e.g. 8.5) -> enable redis, then redeploy."
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

if [ -n "${FORGE_RELEASE_DIRECTORY:-}" ]; then
  cd "${FORGE_RELEASE_DIRECTORY}"
fi
REPO_ROOT="${FORGE_RELEASE_DIRECTORY:-$(pwd)}"

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

# Forge links .env into the release before this script runs; Laravel reads api/.env.
ENV_LINK_TARGET="${API_DIR}/.env"

env_is_usable() {
  [ -n "${1:-}" ] && [ -e "${1}" ] && [ -r "${1}" ]
}

resolve_forge_site_root() {
  local root=""
  if [ -n "${FORGE_SITE_PATH:-}" ]; then
    if [[ "${FORGE_SITE_PATH}" == *"/current"* ]]; then
      root="${FORGE_SITE_PATH%%/current*}"
    else
      root="${FORGE_SITE_PATH}"
    fi
  fi
  if [ -z "${root}" ] || [ ! -d "${root}" ]; then
    root="$(cd "${REPO_ROOT}/../.." 2>/dev/null && pwd || true)"
  fi
  printf '%s' "${root}"
}

FORGE_SITE_ROOT="$(resolve_forge_site_root)"
SHARED_ENV=""

if env_is_usable "${ENV_LINK_TARGET}"; then
  SHARED_ENV="${ENV_LINK_TARGET}"
elif env_is_usable "${REPO_ROOT}/.env"; then
  SHARED_ENV="${REPO_ROOT}/.env"
else
  ENV_CANDIDATES=()
  ENV_CANDIDATES+=("${FORGE_RELEASE_DIRECTORY:-${REPO_ROOT}}/.env")
  ENV_CANDIDATES+=("${REPO_ROOT}/.env")
  ENV_CANDIDATES+=("${FORGE_SITE_ROOT}/.env")
  ENV_CANDIDATES+=("${FORGE_SITE_ROOT}/shared/.env")
  if [ -n "${FORGE_SITE_PATH:-}" ]; then
    ENV_CANDIDATES+=("${FORGE_SITE_PATH}/.env")
    ENV_CANDIDATES+=("${FORGE_SITE_PATH}/shared/.env")
  fi

  CHECKED_PATHS=()
  for CANDIDATE in "${ENV_CANDIDATES[@]}"; do
    [ -z "${CANDIDATE}" ] || [ "${CANDIDATE}" = "/.env" ] && continue
    CHECKED_PATHS+=("${CANDIDATE}")
    if env_is_usable "${CANDIDATE}"; then
      SHARED_ENV="${CANDIDATE}"
      break
    fi
  done

  if [ -z "${SHARED_ENV}" ] && [ -n "${FORGE_SITE_ROOT}" ] && command -v find >/dev/null 2>&1; then
    FOUND_ENV="$(find "${FORGE_SITE_ROOT}" -maxdepth 3 -name '.env' ! -path '*/vendor/*' -readable 2>/dev/null | head -n 1 || true)"
    if env_is_usable "${FOUND_ENV}"; then
      SHARED_ENV="${FOUND_ENV}"
      CHECKED_PATHS+=("${FOUND_ENV} (find)")
    fi
  fi
fi

if [ -z "${SHARED_ENV}" ]; then
  echo "ERROR: Could not locate a readable Forge .env on disk (UI Environment may still be saved)."
  echo "Paths checked:"
  for P in "${CHECKED_PATHS[@]:-}"; do
    echo "  - ${P}"
  done
  echo "Diagnostics:"
  ls -la "${REPO_ROOT}" 2>/dev/null | head -20 || true
  ls -la "${API_DIR}" 2>/dev/null | head -20 || true
  ls -la "${FORGE_SITE_ROOT}" 2>/dev/null | head -20 || true
  exit 1
fi

if [ "${SHARED_ENV}" != "${ENV_LINK_TARGET}" ]; then
  ln -sf "${SHARED_ENV}" "${ENV_LINK_TARGET}"
fi

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
# Boot validated above: route:cache and view:cache boot the full kernel with cached config.
# assert_cached_app_env confirms APP_ENV in bootstrap/cache/config.php.
# $ACTIVATE_RELEASE and $RESTART_QUEUES run in deploy/forge-forge-ui-sandbox.sh (Forge macros).

echo "EIS Bridge sandbox deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo unknown))"
