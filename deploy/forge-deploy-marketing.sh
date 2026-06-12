#!/usr/bin/env bash
# EIS Bridge — static marketing + developer portal deploy for Laravel Forge.
# Paste into Forge → Site → Deployment → Deploy Script (eisbridge.com).
#
# No PHP, Composer, or Node build required — serves index.html, portal/, styles/, docs/.

set -euo pipefail

SITE_PATH="${FORGE_SITE_PATH:-/home/forge/eisbridge.com}"
SITE_BRANCH="${FORGE_SITE_BRANCH:-release/rc1}"

cd "$SITE_PATH"

git fetch --prune origin
git checkout "$SITE_BRANCH"
git pull --ff-only origin "$SITE_BRANCH"

for required_path in index.html portal styles; do
    if [ ! -e "$required_path" ]; then
        echo "Missing required marketing asset: $required_path"
        exit 1
    fi
done

echo "EIS Bridge marketing deploy complete ($(git rev-parse --short HEAD)) on $SITE_BRANCH"