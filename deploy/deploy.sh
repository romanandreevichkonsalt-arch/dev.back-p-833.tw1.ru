#!/usr/bin/env bash
# Деплой back-dev на dev-сервер. Запускать из корня репозитория или deploy/.
#
#   ./deploy/deploy.sh files   # ~1–2 мин — только код (repo → app)
#   ./deploy/deploy.sh app     # ~2–5 мин — код + composer + миграции
#   ./deploy/deploy.sh full    # ~15–20 мин — всё + open-api/generate (по умолчанию)
#
# SSH: ключ ~/.ssh/github_actions_deploy или SSH_IDENTITY_FILE.
# См. deploy/setup-github-actions-key.sh
set -euo pipefail

MODE="${1:-files}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

case "$MODE" in
  files|app|full) ;;
  -h|--help|help)
    sed -n '2,8p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
    ;;
  *)
    echo "ERROR: unknown mode «${MODE}». Use: files | app | full" >&2
    exit 1
    ;;
esac

SSH_HOST="${SSH_HOST:-5.129.203.254}"
SSH_USER="${SSH_USER:-dev_back_p_8_usr}"
SITE_DOMAIN="${SITE_DOMAIN:-dev.back-p-833.tw1.ru}"
DEPLOY_BASE="${DEPLOY_BASE:-/var/www/dev_back_p_8_usr/data}"
REMOTE_REPO="${DEPLOY_BASE}/repo/${SITE_DOMAIN}"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)
if [ -n "${SSH_ASKPASS:-}" ] && [ ! -x "${SSH_ASKPASS}" ]; then
  unset SSH_ASKPASS SSH_ASKPASS_REQUIRE DISPLAY
fi
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/github_actions_deploy" ]; then
  SSH_OPTS+=(-i "${HOME}/.ssh/github_actions_deploy")
fi

ssh_cmd() {
  if [ -n "${SSH_ASKPASS:-}" ]; then
    setsid ssh "${SSH_OPTS[@]}" "$@"
  else
    ssh "${SSH_OPTS[@]}" "$@"
  fi
}

rsync_ssh() {
  if [ -n "${SSH_ASKPASS:-}" ]; then
    echo "setsid ssh ${SSH_OPTS[*]}"
  else
    echo "ssh ${SSH_OPTS[*]}"
  fi
}

RSYNC_EXCLUDES=(
  --filter='P config/db.php'
  --exclude='.git'
  --exclude='.env'
  --exclude='.env.*'
  --exclude='config/db.php'
  --exclude='runtime/'
  --exclude='vendor/'
  --exclude='web/assets/'
  --exclude='web/uploads/'
  --exclude='deploy/.ssh-askpass.sh'
)

echo "→ Режим: ${MODE}"
echo "→ SSH ${SSH_USER}@${SSH_HOST}..."
ssh_cmd "${SSH_USER}@${SSH_HOST}" "echo connected"

echo "→ Rsync в ${REMOTE_REPO}..."
ssh_cmd "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_REPO}'"
rsync -az --delete \
  "${RSYNC_EXCLUDES[@]}" \
  -e "$(rsync_ssh)" \
  "${ROOT}/" "${SSH_USER}@${SSH_HOST}:${REMOTE_REPO}/"

echo "→ deploy-backend.sh ${MODE}..."
ssh_cmd "${SSH_USER}@${SSH_HOST}" \
  "bash '${REMOTE_REPO}/deploy/deploy-backend.sh' '${SITE_DOMAIN}' '${MODE}'"

echo "✓ Деплой (${MODE}) завершён: https://${SITE_DOMAIN}"
