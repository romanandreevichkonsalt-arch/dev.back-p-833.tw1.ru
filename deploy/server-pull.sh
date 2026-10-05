#!/usr/bin/env bash
# Запускать НА СЕРВЕРЕ (FastPanel → SSH) под пользователем dev_back_p_8_usr.
# Скачивает master с GitHub и выкатывает в app/.
#
#   export GITHUB_TOKEN=ghp_...   # classic token, repo read
#   bash deploy/server-pull.sh
set -euo pipefail

DOMAIN="${SITE_DOMAIN:-dev.back-p-833.tw1.ru}"
USER_HOME="/var/www/$(whoami)/data"
REPO_DIR="${USER_HOME}/repo/${DOMAIN}"
TMP="${USER_HOME}/tmp/back-dev-pull-$$"
REPO="${GITHUB_REPO:-romanandreevichkonsalt-arch/dev.back-p-833.tw1.ru}"
BRANCH="${GITHUB_BRANCH:-master}"

if [ -z "${GITHUB_TOKEN:-}" ]; then
  echo "ERROR: export GITHUB_TOKEN=... (GitHub PAT с доступом к репозиторию)"
  exit 1
fi

mkdir -p "$(dirname "$TMP")" "$REPO_DIR"
rm -rf "$TMP"
mkdir -p "$TMP"

echo "→ Скачивание ${REPO}@${BRANCH}..."
curl -fsSL \
  -H "Authorization: Bearer ${GITHUB_TOKEN}" \
  -H "Accept: application/vnd.github+json" \
  "https://api.github.com/repos/${REPO}/tarball/${BRANCH}" \
  | tar -xz -C "$TMP" --strip-components=1

echo "→ Rsync в ${REPO_DIR}..."
rsync -a --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='config/db.php' \
  --exclude='runtime/' \
  --exclude='vendor/' \
  --exclude='web/assets/' \
  --exclude='web/uploads/' \
  "${TMP}/" "${REPO_DIR}/"

rm -rf "$TMP"

echo "→ deploy-backend.sh full..."
bash "${REPO_DIR}/deploy/deploy-backend.sh" "${DOMAIN}" full

echo "✓ Готово: https://${DOMAIN}"
