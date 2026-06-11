#!/usr/bin/env bash
# =============================================================================
# PASTE THIS ENTIRE FILE into Forge -> sandbox.eisbridge.com -> Deployment
# -> Deploy Script -> Save. (Only 5 lines — never paste forge-deploy-sandbox.sh)
#
# Each deploy runs the latest deploy/forge-deploy-sandbox.sh from git automatically.
# =============================================================================
set -euo pipefail

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY"

if [ -f deploy/forge-deploy-sandbox.sh ]; then
  bash deploy/forge-deploy-sandbox.sh
elif [ -f ../deploy/forge-deploy-sandbox.sh ]; then
  bash ../deploy/forge-deploy-sandbox.sh
else
  echo "ERROR: deploy/forge-deploy-sandbox.sh not found in release."
  echo "Forge root should be / (repo root) with web api/public, or / with web public if root is api."
  exit 1
fi
