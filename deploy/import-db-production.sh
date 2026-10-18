#!/usr/bin/env bash
# Импорт SQL на production (api.mf-anna.ru). Не заливать случайно дамп со staging без процедуры.
#   CONFIRM=1 ./deploy/import-db-production.sh /path/to/dump.sql
set -euo pipefail

if [ "${CONFIRM:-}" != "1" ]; then
  echo "ERROR: import on production requires CONFIRM=1" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export SSH_HOST="${SSH_HOST:-161.104.62.214}"
export SSH_USER="${SSH_USER:-api_mf_anna__usr}"
export SITE_DOMAIN="${SITE_DOMAIN:-api.mf-anna.ru}"
export DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_mf_anna__usr/data}"

exec "${ROOT}/deploy/import-db-remote.sh" "$@"
