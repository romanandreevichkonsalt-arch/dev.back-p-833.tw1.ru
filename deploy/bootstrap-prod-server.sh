#!/usr/bin/env bash
# Первичная настройка prod на сервере FastPanel. Запускать ПО SSH под api_dev_fabr_usr:
#   bash bootstrap-prod-server.sh /path/to/.env
#
# .env — локальный файл на сервере (не коммитить), см. .env.example в корне репозитория.
set -euo pipefail

ENV_FILE="${1:?Usage: bootstrap-prod-server.sh /path/to/.env}"

DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
GITHUB_REPO="${GITHUB_REPO:-romanandreevichkonsalt-arch/dev.back-p-833.tw1.ru}"
GITHUB_BRANCH="${GITHUB_BRANCH:-main}"

USER_HOME="/var/www/$(whoami)/data"
REPO_DIR="${USER_HOME}/repo/${DOMAIN}"
APP_ROOT="${USER_HOME}/app/${DOMAIN}"
WWW_ROOT="${USER_HOME}/www/${DOMAIN}"

if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: env file not found: $ENV_FILE" >&2
  exit 1
fi

mkdir -p "${USER_HOME}/repo" "${USER_HOME}/app" "${USER_HOME}/www"

if [ ! -d "${REPO_DIR}/.git" ]; then
  echo "→ git clone ${GITHUB_REPO} (${GITHUB_BRANCH})..."
  git clone --branch "${GITHUB_BRANCH}" "https://github.com/${GITHUB_REPO}.git" "${REPO_DIR}"
else
  echo "→ git pull..."
  git -C "${REPO_DIR}" fetch origin "${GITHUB_BRANCH}"
  git -C "${REPO_DIR}" checkout "${GITHUB_BRANCH}"
  git -C "${REPO_DIR}" pull --ff-only origin "${GITHUB_BRANCH}"
fi

install -m 600 "$ENV_FILE" "${APP_ROOT}/.env"
mkdir -p "${APP_ROOT}"

echo "→ deploy-backend.sh full..."
bash "${REPO_DIR}/deploy/deploy-backend.sh" "${DOMAIN}" full

echo ""
echo "✓ Bootstrap done: https://${DOMAIN}"
echo "  Repo (git pull): ${REPO_DIR}"
echo "  App: ${APP_ROOT}"
echo "  Document root symlink: ${WWW_ROOT} → ${APP_ROOT}/web"
echo ""
echo "Обновления: bash ${REPO_DIR}/deploy/server-git-update.sh"
