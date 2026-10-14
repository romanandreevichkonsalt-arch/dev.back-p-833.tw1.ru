#!/usr/bin/env bash
# На prod-сервере: git pull + выкатка. Usage:
#   bash deploy/server-git-update.sh [files|app|full]
set -euo pipefail

DOMAIN="${SITE_DOMAIN:-api.dev-fabrika.ru}"
MODE="${1:-files}"
BRANCH="${GITHUB_BRANCH:-main}"

USER_HOME="/var/www/$(whoami)/data"
REPO_DIR="${USER_HOME}/repo/${DOMAIN}"

if [ ! -d "${REPO_DIR}/.git" ]; then
  echo "ERROR: ${REPO_DIR} is not a git repo. Run bootstrap-prod-server.sh first." >&2
  exit 1
fi

echo "→ git pull origin ${BRANCH}..."
git -C "${REPO_DIR}" fetch origin "${BRANCH}"
git -C "${REPO_DIR}" checkout "${BRANCH}"
git -C "${REPO_DIR}" pull --ff-only origin "${BRANCH}"

bash "${REPO_DIR}/deploy/deploy-backend.sh" "${DOMAIN}" "${MODE}"
echo "✓ ${MODE} deploy done: https://${DOMAIN}"
