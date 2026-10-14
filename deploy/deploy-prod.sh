#!/usr/bin/env bash
# Деплой api.dev-fabrika.ru (prod). Из корня репозитория:
#   ./deploy/deploy-prod.sh app
#
# SSH: ключ в authorized_keys пользователя api_dev_fabr_usr (FastPanel → SSH).
# Опционально: SSH_IDENTITY_FILE=~/.ssh/id_ed25519
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

export SSH_HOST="${SSH_HOST:-161.104.62.214}"
export SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
export SITE_DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
export DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_dev_fabr_usr/data}"

if [ -z "${SSH_IDENTITY_FILE:-}" ] && [ -f "${HOME}/.ssh/id_ed25519" ]; then
  export SSH_IDENTITY_FILE="${HOME}/.ssh/id_ed25519"
fi

exec "${ROOT}/deploy/deploy.sh" "${1:-app}"
