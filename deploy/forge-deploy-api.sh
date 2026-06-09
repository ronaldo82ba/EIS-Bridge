#!/usr/bin/env bash
# EIS Bridge — Laravel API deploy script for Laravel Forge.
# Paste into Forge → Site → Deployment → Deploy Script (api.eisbridge.com).
#
# Forge injects: FORGE_SITE_PATH, FORGE_SITE_BRANCH, FORGE_COMPOSER
# Web directory in Forge must be: api/public (relative to repo root)

set -euo pipefail

REPO_ROOT="${FORGE_SITE_PATH:-/home/forge/api.eisbridge.com}"
API_DIR="${REPO_ROOT}/api"

cd "${REPO_ROOT}"

if [ ! -f "${API_DIR}/.env" ]; then
  echo "ERROR: ${API_DIR}/.env not found. Create it in Forge → Site → Environment first."
  exit 1
fi

git pull origin "${FORGE_SITE_BRANCH:-main}"

cd "${API_DIR}"

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f package-lock.json ]; then
  npm ci --ignore-scripts
  npm run build
fi

php artisan migrate --force

php artisan storage:link --force 2>/dev/null || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan horizon:terminate 2>/dev/null || php artisan queue:restart 2>/dev/null || true

echo "EIS Bridge API deploy complete ($(git -C "${REPO_ROOT}" rev-parse --short HEAD))"