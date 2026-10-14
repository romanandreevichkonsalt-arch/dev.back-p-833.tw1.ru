#!/usr/bin/env bash
# Запуск sync-uploads-from-dev.sh на prod через SSH.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SSH_HOST="${SSH_HOST:-161.104.62.214}"
SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
IDENT="${SSH_IDENTITY_FILE:-${HOME}/.ssh/id_ed25519}"

rsync -az -e "ssh -o IdentitiesOnly=yes -i $IDENT" \
  "${ROOT}/deploy/sync-uploads-from-dev.sh" \
  "${SSH_USER}@${SSH_HOST}:/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/deploy/"

ssh -o IdentitiesOnly=yes -i "$IDENT" "${SSH_USER}@${SSH_HOST}" \
  'chmod +x /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/deploy/sync-uploads-from-dev.sh && \
   nohup bash /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/deploy/sync-uploads-from-dev.sh \
   >> /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/runtime/sync-uploads.nohup.log 2>&1 & echo started_pid=$!'

echo "→ Follow: ssh ... tail -f .../runtime/sync-uploads.log"
