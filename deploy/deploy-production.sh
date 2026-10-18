#!/usr/bin/env bash
# Production API (пользователи): https://api.mf-anna.ru · ветка main
#   CONFIRM=1 ./deploy/deploy-production.sh app
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [ "${CONFIRM:-}" != "1" ]; then
  echo "ERROR: production deploy to https://api.mf-anna.ru requires explicit confirmation." >&2
  echo "  CONFIRM=1 ./deploy/deploy-production.sh files|app|full" >&2
  exit 1
fi

if [ "${SKIP_BRANCH_CHECK:-}" != "1" ] && command -v git >/dev/null 2>&1; then
  if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    BRANCH="$(git -C "$ROOT" rev-parse --abbrev-ref HEAD)"
    if [ "$BRANCH" != "main" ]; then
      echo "ERROR: production deploy expected from branch «main», current: «${BRANCH}»." >&2
      echo "  checkout main, or SKIP_BRANCH_CHECK=1 CONFIRM=1 ..." >&2
      exit 1
    fi
  fi
fi

export SSH_HOST="${SSH_HOST:-161.104.62.214}"
export SSH_USER="${SSH_USER:-api_mf_anna__usr}"
export SITE_DOMAIN="${SITE_DOMAIN:-api.mf-anna.ru}"
export DEPLOY_BASE="${DEPLOY_BASE:-/var/www/api_mf_anna__usr/data}"

if [ -z "${SSH_IDENTITY_FILE:-}" ] && [ -f "${HOME}/.ssh/id_ed25519" ]; then
  export SSH_IDENTITY_FILE="${HOME}/.ssh/id_ed25519"
fi

echo "→ PRODUCTION target: https://${SITE_DOMAIN} (${SSH_USER}@${SSH_HOST})"
exec "${ROOT}/deploy/deploy.sh" "${1:-app}"
