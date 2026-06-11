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

assert_valid_app_key() {
  local ENV_FILE="$1"
  local RESULT
  RESULT=$("${FORGE_PHP_BIN}" -r "
    \$lines = @file('${ENV_FILE}', FILE_IGNORE_NEW_LINES);
    if (\$lines === false) { echo 'read_fail'; exit(0); }
    \$raw = '';
    foreach (\$lines as \$line) {
      if (preg_match('/^\\s*APP_KEY\\s*=\\s*(.*)\$/', \$line, \$m)) {
        \$raw = trim(\$m[1], \" \\\"'\");
      }
    }
    if (\$raw === '') { echo 'missing'; exit(0); }
    if (!str_starts_with(\$raw, 'base64:')) { echo 'no_prefix'; exit(0); }
    \$decoded = base64_decode(substr(\$raw, 7), true);
    if (\$decoded === false) { echo 'bad_b64'; exit(0); }
    echo strlen(\$decoded);
  " 2>/dev/null || echo error)

  case "${RESULT}" in
    missing)
      echo "ERROR: APP_KEY is not set in ${ENV_FILE}."
      ;;
    no_prefix)
      echo "ERROR: APP_KEY must start with base64: (Laravel AES-256-CBC format)."
      ;;
    bad_b64)
      echo "ERROR: APP_KEY base64 payload is invalid."
      ;;
    read_fail|error)
      echo "ERROR: Could not read or validate APP_KEY in ${ENV_FILE}."
      ;;
    32)
      return 0
      ;;
    *)
      echo "ERROR: APP_KEY decodes to ${RESULT} bytes, expected 32 for AES-256-CBC."
      echo "A wrong-length key causes /up to fail with: Unsupported cipher or incorrect key length."
      ;;
  esac
  echo "Generate a valid key locally: cd api && php artisan key:generate --show"
  echo "Paste the full line (base64:...) into Forge -> Site -> Environment, Save, then redeploy."
  exit 1
}

if [ -z "${FORGE_RELEASE_DIRECTORY:-}" ]; then
  echo "ERROR: FORGE_RELEASE_DIRECTORY is not set. Run via deploy/forge-forge-ui-sandbox.sh (Forge CREATE_RELEASE)."
  exit 1
fi
cd "${FORGE_RELEASE_DIRECTORY}"
REPO_ROOT="${FORGE_RELEASE_DIRECTORY}"

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

resolve_forge_site_root() {
  local root=""
  if [ -n "${FORGE_SITE_PATH:-}" ]; then
    root="${FORGE_SITE_PATH%%/current*}"
  fi
  if [ -z "${root}" ] || [ ! -d "${root}" ]; then
    root="$(cd "${REPO_ROOT}/../.." 2>/dev/null && pwd || true)"
  fi
  printf '%s' "${root}"
}

report_env_diagnostics() {
  echo "Diagnostics:"
  echo "  FORGE_RELEASE_DIRECTORY=${FORGE_RELEASE_DIRECTORY}"
  echo "  FORGE_SITE_PATH=${FORGE_SITE_PATH:-<unset>}"
  echo "  REPO_ROOT=${REPO_ROOT}"
  echo "  API_DIR=${API_DIR}"
  echo "  FORGE_SITE_ROOT=${FORGE_SITE_ROOT:-<unset>}"
  echo "  ls -la REPO_ROOT:"
  ls -la "${REPO_ROOT}" 2>&1 || true
  echo "  ls -la API_DIR:"
  ls -la "${API_DIR}" 2>&1 || true
  if [ -n "${FORGE_SITE_ROOT:-}" ] && [ -d "${FORGE_SITE_ROOT}" ]; then
    echo "  ls -la FORGE_SITE_ROOT:"
    ls -la "${FORGE_SITE_ROOT}" 2>&1 || true
  fi
}

FORGE_SITE_ROOT="$(resolve_forge_site_root)"
CHECKED_PATHS=()

if [ -e "${ENV_LINK_TARGET}" ]; then
  echo "Using existing ${ENV_LINK_TARGET} (Forge-linked into release)."
elif [ -e "${REPO_ROOT}/.env" ] && [ "${API_DIR}" != "${REPO_ROOT}" ]; then
  ln -sf "${REPO_ROOT}/.env" "${ENV_LINK_TARGET}"
  echo "Linked ${ENV_LINK_TARGET} -> ${REPO_ROOT}/.env"
else
  SHARED_ENV=""

  ENV_CANDIDATES=()
  ENV_CANDIDATES+=("${REPO_ROOT}/.env")
  ENV_CANDIDATES+=("${API_DIR}/.env")
  ENV_CANDIDATES+=("${FORGE_SITE_ROOT}/.env")
  ENV_CANDIDATES+=("${FORGE_SITE_ROOT}/shared/.env")
  if [ -n "${FORGE_SITE_PATH:-}" ]; then
    ENV_CANDIDATES+=("${FORGE_SITE_PATH}/.env")
    ENV_CANDIDATES+=("${FORGE_SITE_PATH}/shared/.env")
    ENV_CANDIDATES+=("$(dirname "${FORGE_SITE_PATH}")/.env")
  fi

  for CANDIDATE in "${ENV_CANDIDATES[@]}"; do
    [ -z "${CANDIDATE}" ] || [ "${CANDIDATE}" = "/.env" ] && continue
    CHECKED_PATHS+=("${CANDIDATE}")
    if [ -e "${CANDIDATE}" ]; then
      SHARED_ENV="${CANDIDATE}"
      break
    fi
  done

  if [ -z "${SHARED_ENV}" ] && [ -n "${FORGE_SITE_ROOT}" ] && [ -d "${FORGE_SITE_ROOT}" ]; then
    while IFS= read -r FOUND; do
      [ -z "${FOUND}" ] && continue
      CHECKED_PATHS+=("${FOUND} (find)")
      if [ -e "${FOUND}" ]; then
        SHARED_ENV="${FOUND}"
        break
      fi
    done < <(find "${FORGE_SITE_ROOT}" -maxdepth 3 -name '.env' -not -path '*/vendor/*' 2>/dev/null)
  fi

  if [ -z "${SHARED_ENV}" ]; then
    echo "ERROR: .env not found for sandbox deploy."
    echo "Forge -> Site -> Environment: paste env, click Save, then redeploy."
    echo "Paths checked:"
    for P in "${CHECKED_PATHS[@]}"; do
      echo "  - ${P}"
    done
    report_env_diagnostics
    exit 1
  fi

  ln -sf "${SHARED_ENV}" "${ENV_LINK_TARGET}"
  echo "Linked ${ENV_LINK_TARGET} -> ${SHARED_ENV}"
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

assert_valid_app_key "${ENV_LINK_TARGET}"

${FORGE_PHP_BIN} artisan config:clear
${FORGE_PHP_BIN} artisan config:cache
assert_cached_app_env staging
${FORGE_PHP_BIN} artisan route:cache
${FORGE_PHP_BIN} artisan view:cache
# Boot validated above: route:cache and view:cache boot the full kernel with cached config.
# assert_cached_app_env confirms APP_ENV in bootstrap/cache/config.php.
# $ACTIVATE_RELEASE and $RESTART_QUEUES run in deploy/forge-forge-ui-sandbox.sh (Forge macros).
# horizon:terminate here stops the previous release's workers before activation/restart.
${FORGE_PHP_BIN} artisan horizon:terminate 2>/dev/null || true

echo "EIS Bridge sandbox deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD 2>/dev/null || echo unknown))"
