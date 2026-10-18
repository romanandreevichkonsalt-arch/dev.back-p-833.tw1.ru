#!/usr/bin/env bash
# Экспорт БД с сервера (читает app/.env). Локально:
#   ./deploy/export-db-remote.sh > staging.sql
# Переменные как у deploy-staging.sh / deploy-production.sh (или export SSH_USER=…).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-}"

SSH_HOST="${SSH_HOST:-161.104.62.214}"
SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
SITE_DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_dev_fabr_usr/data}"
APP_ROOT="${DEPLOY_BASE}/app/${SITE_DOMAIN}"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

echo "→ mysqldump from ${SITE_DOMAIN} (${SSH_USER}@${SSH_HOST})..." >&2

run_remote() {
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" bash -s <<EOF
set -euo pipefail
APP_ROOT='${APP_ROOT}'
ENV_FILE="\${APP_ROOT}/.env"
if [ ! -f "\$ENV_FILE" ]; then
  echo "ERROR: \$ENV_FILE missing." >&2
  exit 1
fi
DB_DSN=\$(grep -E '^DB_DSN=' "\$ENV_FILE" | cut -d= -f2-)
DB_USER=\$(grep -E '^DB_USER=' "\$ENV_FILE" | cut -d= -f2-)
DB_PASSWORD=\$(grep -E '^DB_PASSWORD=' "\$ENV_FILE" | cut -d= -f2-)
DB_NAME=\$(echo "\$DB_DSN" | sed -n 's/.*dbname=\\([^;]*\\).*/\\1/p')
mysqldump -u "\$DB_USER" -p"\$DB_PASSWORD" --single-transaction --routines --triggers "\$DB_NAME"
EOF
}

if [ -n "$OUT" ]; then
  run_remote > "$OUT"
  echo "✓ Written: $OUT" >&2
else
  run_remote
fi
