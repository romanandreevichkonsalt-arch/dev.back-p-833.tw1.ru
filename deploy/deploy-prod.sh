#!/usr/bin/env bash
# Deprecated: use ./deploy/deploy-staging.sh (staging) or ./deploy/deploy-production.sh (prod).
set -euo pipefail

echo "NOTE: deploy-prod.sh → staging (api.dev-fabrika.ru). Use deploy-staging.sh or deploy-production.sh." >&2
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "${ROOT}/deploy/deploy-staging.sh" "${1:-app}"
