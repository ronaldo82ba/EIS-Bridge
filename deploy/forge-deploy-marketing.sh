#!/usr/bin/env bash
# EIS Bridge — static marketing + developer portal deploy for Laravel Forge.
# Paste into Forge → Site → Deployment → Deploy Script (eisbridge.com).
#
# No PHP, Composer, or Node build required.
# Uses sparse checkout (--no-cone) so root files and allowlisted public docs deploy.

set -euo pipefail

SITE_PATH="${FORGE_SITE_PATH:-/home/forge/eisbridge.com}"
SITE_BRANCH="${FORGE_SITE_BRANCH:-release/rc1}"

cd "$SITE_PATH"

git fetch --prune origin
git sparse-checkout init --no-cone
git sparse-checkout set \
    /index.html \
    /privacy.html \
    /terms.html \
    portal \
    styles \
    assets \
    /docs/partner-program.md \
    /docs/certification-playbook.md \
    /docs/vendor-api.md \
    /docs/qa/integration-test-cases-v1.md \
    docs/postman \
    /docs/schemas/sale-object.schema.json
git checkout "$SITE_BRANCH"
git reset --hard "origin/$SITE_BRANCH"
git clean -fdx

for required_path in index.html portal styles privacy.html terms.html; do
    if [ ! -e "$required_path" ]; then
        echo "Missing required marketing asset: $required_path"
        exit 1
    fi
done

for required_doc in \
    docs/partner-program.md \
    docs/certification-playbook.md \
    docs/vendor-api.md \
    docs/qa/integration-test-cases-v1.md \
    docs/postman/EIS-Bridge-API-v1.postman_collection.json \
    docs/schemas/sale-object.schema.json
do
    if [ ! -e "$required_doc" ]; then
        echo "Missing required public doc: $required_doc"
        exit 1
    fi
done

# Defense in depth: fail if internal docs leaked into the web root.
for forbidden_path in \
    docs/FORGE_DEPLOYMENT.md \
    docs/risk-report-2026-06-10.md \
    docs/qa/sandbox-results-2026-06-12.md
do
    if [ -e "$forbidden_path" ]; then
        echo "Forbidden artifact present after deploy: $forbidden_path"
        exit 1
    fi
done
if compgen -G "RELEASE_NOTES*" > /dev/null; then
    echo "Forbidden artifact present after deploy: RELEASE_NOTES*"
    exit 1
fi

echo "EIS Bridge marketing deploy complete ($(git rev-parse --short HEAD)) on $SITE_BRANCH"
