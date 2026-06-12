#!/usr/bin/env bash
# EIS Bridge — static marketing + developer portal deploy for Laravel Forge.
# Paste into Forge → Site → Deployment → Deploy Script (eisbridge.com).
#
# No PHP, Composer, or Node build required.
# Uses sparse checkout so only public marketing assets are deployed.

set -euo pipefail

SITE_PATH="${FORGE_SITE_PATH:-/home/forge/eisbridge.com}"
SITE_BRANCH="${FORGE_SITE_BRANCH:-release/rc1}"

cd "$SITE_PATH"

git fetch --prune origin
git sparse-checkout init --cone
git sparse-checkout set \
    index.html \
    privacy.html \
    terms.html \
    portal \
    styles \
    assets
git checkout "$SITE_BRANCH"
git reset --hard "origin/$SITE_BRANCH"
git clean -fdx

for required_path in index.html portal styles privacy.html terms.html; do
    if [ ! -e "$required_path" ]; then
        echo "Missing required marketing asset: $required_path"
        exit 1
    fi
done

# Defense in depth: ensure no internal documentation artifacts
# are left in the deployed web root, even if prior deploys had them.
rm -rf docs

echo "EIS Bridge marketing deploy complete ($(git rev-parse --short HEAD)) on $SITE_BRANCH"