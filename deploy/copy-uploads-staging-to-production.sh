#!/usr/bin/env bash
# Перенос web/uploads staging → production через локальную машину (два rsync).
#   CONFIRM=1 ./deploy/copy-uploads-staging-to-production.sh
set -euo pipefail

if [ "${CONFIRM:-}" != "1" ]; then
  echo "ERROR: requires CONFIRM=1" >&2
  exit 1
fi

SSH_HOST="${SSH_HOST:-161.104.62.214}"
STAGING_USER="${STAGING_USER:-api_dev_fabr_usr}"
PROD_USER="${PROD_USER:-api_mf_anna__usr}"
STAGING_UPLOADS="/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web/uploads/"
PROD_UPLOADS="/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/web/uploads/"
LOCAL_DIR="${TMPDIR:-/tmp}/mf-anna-uploads-migrate-$$"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=30)
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

RSYNC_RSH="ssh ${SSH_OPTS[*]}"
trap 'rm -rf "$LOCAL_DIR"' EXIT

mkdir -p "$LOCAL_DIR"

echo "→ Pull uploads from staging..."
rsync -a -e "$RSYNC_RSH" "${STAGING_USER}@${SSH_HOST}:${STAGING_UPLOADS}" "$LOCAL_DIR/"

echo "→ Push uploads to production..."
ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "mkdir -p '${PROD_UPLOADS}'"
rsync -a -e "$RSYNC_RSH" "$LOCAL_DIR/" "${PROD_USER}@${SSH_HOST}:${PROD_UPLOADS}"

echo "✓ Uploads copied staging → production"
