#!/usr/bin/env bash
# Thin wrapper — canonical Forge deploy script lives at repo root (sparse-checkout safe).
exec bash "$(git rev-parse --show-toplevel)/marketing-deploy.sh" "$@"
