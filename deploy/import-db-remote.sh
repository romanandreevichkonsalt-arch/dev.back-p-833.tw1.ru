#!/usr/bin/env bash
# Импорт SQL на prod через SSH. Локально из корня репозитория:
#   ./deploy/import-db-remote.sh /path/to/dump.sql
#
# Переменные как у deploy-staging.sh / deploy-production.sh; на сервере читается app/.env (DB_*).
set -euo pipefail

DUMP="${1:?Usage: import-db-remote.sh /path/to/dump.sql}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -f "$DUMP" ]; then
  echo "ERROR: file not found: $DUMP" >&2
  exit 1
fi
if ! head -c 512 "$DUMP" | grep -qE '^(--- |CREATE |INSERT |DROP |/\*)'; then
  echo "ERROR: $DUMP does not look like SQL (maybe saved phpMyAdmin HTML?). Export → SQL from phpMyAdmin." >&2
  exit 1
fi

SSH_HOST="${SSH_HOST:-161.104.62.214}"
SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
SITE_DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_dev_fabr_usr/data}"
APP_ROOT="${DEPLOY_BASE}/app/${SITE_DOMAIN}"
REMOTE_DUMP="/tmp/import-$$.sql"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

echo "→ Upload dump..."
scp "${SSH_OPTS[@]}" "$DUMP" "${SSH_USER}@${SSH_HOST}:${REMOTE_DUMP}"

echo "→ Import into MySQL (from ${APP_ROOT}/.env)..."
ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" bash -s <<EOF
set -euo pipefail
APP_ROOT='${APP_ROOT}'
REMOTE_DUMP='${REMOTE_DUMP}'
ENV_FILE="\${APP_ROOT}/.env"
if [ ! -f "\$ENV_FILE" ]; then
  echo "ERROR: \$ENV_FILE missing. Run bootstrap-prod-server.sh first." >&2
  exit 1
fi
DB_DSN=\$(grep -E '^DB_DSN=' "\$ENV_FILE" | cut -d= -f2-)
DB_USER=\$(grep -E '^DB_USER=' "\$ENV_FILE" | cut -d= -f2-)
DB_PASSWORD=\$(grep -E '^DB_PASSWORD=' "\$ENV_FILE" | cut -d= -f2-)
DB_NAME=\$(echo "\$DB_DSN" | sed -n 's/.*dbname=\\([^;]*\\).*/\\1/p')
mysql -u "\$DB_USER" -p"\$DB_PASSWORD" "\$DB_NAME" < "\$REMOTE_DUMP"
rm -f "\$REMOTE_DUMP"
echo "✓ Import done"
EOF

echo "✓ Database imported on ${SITE_DOMAIN}"
