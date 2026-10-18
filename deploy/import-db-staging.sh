#!/usr/bin/env bash
# Импорт SQL на staging (api.dev-fabrika.ru).
#   ./deploy/import-db-staging.sh /path/to/dump.sql
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export SSH_HOST="${SSH_HOST:-161.104.62.214}"
export SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
export SITE_DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
export DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_dev_fabr_usr/data}"

exec "${ROOT}/deploy/import-db-remote.sh" "$@"
