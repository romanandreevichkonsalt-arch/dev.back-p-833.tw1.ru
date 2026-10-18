#!/usr/bin/env bash
# Одноразовый перенос БД staging → production (через локальный дамп).
#   CONFIRM=1 ./deploy/copy-db-staging-to-production.sh
set -euo pipefail

if [ "${CONFIRM:-}" != "1" ]; then
  echo "ERROR: requires CONFIRM=1" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DUMP="${TMPDIR:-/tmp}/mf-anna-db-migrate-$$.sql"
trap 'rm -f "$DUMP"' EXIT

echo "→ Export staging (api.dev-fabrika.ru)..."
(
  export SSH_USER=api_dev_fabr_usr
  export SITE_DOMAIN=api.dev-fabrika.ru
  export DEPLOY_BASE=/var/www/api_dev_fabr_usr/data
  "${ROOT}/deploy/export-db-remote.sh" "$DUMP"
)

echo "→ Import production (api.mf-anna.ru)..."
CONFIRM=1 "${ROOT}/deploy/import-db-production.sh" "$DUMP"

echo "✓ Database copied staging → production"
