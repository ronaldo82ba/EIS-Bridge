#!/usr/bin/env bash
# EIS Bridge — static marketing + developer portal deploy for Laravel Forge.
# Paste into Forge → Site → Deployment → Deploy Script (eisbridge.com).
#
# No PHP, Composer, or Node build required — serves index.html, portal/, styles/, docs/.

set -euo pipefail

cd "${FORGE_SITE_PATH:-/home/forge/eisbridge.com}"

git pull origin "${FORGE_SITE_BRANCH:-main}"

echo "EIS Bridge marketing deploy complete ($(git rev-parse --short HEAD))"