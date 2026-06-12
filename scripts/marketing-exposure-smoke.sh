#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-https://eisbridge.com}"

assert_status() {
    local path="$1"
    local expected="$2"
    local url="${BASE_URL%/}${path}"
    local status
    status="$(curl -sS -o /dev/null -w "%{http_code}" "$url" || true)"

    if [ "$status" != "$expected" ]; then
        echo "FAIL: ${url} -> expected ${expected}, got ${status}"
        return 1
    fi
    echo "PASS: ${url} -> ${status}"
}

echo "Running marketing exposure smoke checks against ${BASE_URL}"

# Public pages/docs
assert_status "/" 200
assert_status "/partner.html" 200
assert_status "/docs/partner-program.md" 200
assert_status "/docs/certification-playbook.md" 200
assert_status "/docs/vendor-api.md" 200
assert_status "/docs/qa/integration-test-cases-v1.md" 200
assert_status "/docs/postman/EIS-Bridge-API-v1.postman_collection.json" 200
assert_status "/docs/schemas/sale-object.schema.json" 200

# Blocked/internal
assert_status "/.git/config" 404
assert_status "/.env" 404
assert_status "/marketing-deploy.sh" 404
assert_status "/deploy/nginx/marketing-security.conf" 404
assert_status "/api/.env" 404
assert_status "/scripts/connect-forge.ps1" 404
assert_status "/docs/FORGE_DEPLOYMENT.md" 404
assert_status "/README.md" 404

echo "All marketing exposure checks passed."
