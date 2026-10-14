#!/usr/bin/env bash
# Полная установка на prod. Запуск в терминале FastPanel под api_dev_fabr_usr
# (Сайты → api.dev-fabrika.ru → Терминал) или по SSH после добавления ключа.
#
# Перед запуском задайте переменные (пароли не хранятся в git):
#   export FABRICA_DB_PASSWORD='...'
#   export FABRICA_DEV_SSH_KEY='ssh-ed25519 AAAA...'   # опционально: ключ для деплоя с вашего ПК
set -euo pipefail

DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
GITHUB_REPO="${GITHUB_REPO:-romanandreevichkonsalt-arch/dev.back-p-833.tw1.ru}"
GITHUB_BRANCH="${GITHUB_BRANCH:-main}"
DB_NAME="${FABRICA_DB_NAME:-api_dev_fabr}"
DB_USER="${FABRICA_DB_USER:-api_dev_fabr}"
DB_PASSWORD="${FABRICA_DB_PASSWORD:?Set FABRICA_DB_PASSWORD}"
FRONTEND_URL="${FRONTEND_URL:-https://dev-fabrika.ru}"

USER_HOME="/var/www/$(whoami)/data"
REPO_DIR="${USER_HOME}/repo/${DOMAIN}"
APP_ROOT="${USER_HOME}/app/${DOMAIN}"
WWW_ROOT="${USER_HOME}/www/${DOMAIN}"
ENV_FILE="${APP_ROOT}/.env"

if [ -n "${FABRICA_DEV_SSH_KEY:-}" ]; then
  mkdir -p ~/.ssh
  chmod 700 ~/.ssh
  touch ~/.ssh/authorized_keys
  chmod 600 ~/.ssh/authorized_keys
  grep -qF "${FABRICA_DEV_SSH_KEY}" ~/.ssh/authorized_keys \
    || echo "${FABRICA_DEV_SSH_KEY}" >> ~/.ssh/authorized_keys
  echo "→ SSH pubkey added for local deploy"
fi

mkdir -p "${USER_HOME}/repo" "${USER_HOME}/app" "${USER_HOME}/www"

if [ ! -d "${REPO_DIR}/.git" ]; then
  echo "→ git clone..."
  git clone --branch "${GITHUB_BRANCH}" "https://github.com/${GITHUB_REPO}.git" "${REPO_DIR}"
else
  echo "→ git pull..."
  git -C "${REPO_DIR}" fetch origin "${GITHUB_BRANCH}"
  git -C "${REPO_DIR}" checkout "${GITHUB_BRANCH}"
  git -C "${REPO_DIR}" pull --ff-only origin "${GITHUB_BRANCH}"
fi

mkdir -p "${APP_ROOT}"
cat > "${ENV_FILE}" <<EOF
DB_DSN=mysql:host=localhost;dbname=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
FRONTEND_URL=${FRONTEND_URL}
EOF
chmod 600 "${ENV_FILE}"

echo "→ deploy-backend.sh full..."
bash "${REPO_DIR}/deploy/deploy-backend.sh" "${DOMAIN}" full

if [ -n "${FABRICA_SQL_DUMP:-}" ] && [ -f "${FABRICA_SQL_DUMP}" ]; then
  echo "→ import SQL ${FABRICA_SQL_DUMP}..."
  mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < "${FABRICA_SQL_DUMP}"
  echo "→ import done"
fi

echo ""
echo "✓ Install complete: https://${DOMAIN}"
echo "  Test: curl -sI https://${DOMAIN}/api/v1/catalog/menu | head -3"
echo "  Updates: bash ${REPO_DIR}/deploy/server-git-update.sh app"
