#!/usr/bin/env bash
# Скачать медиатеку с VPS в локальный .tar.gz (из корня репозитория).
#
#   ./deploy/backup-media-to-local.sh
#   OUT=~/backups/fabrica ./deploy/backup-media-to-local.sh
#
# Нужен SSH к api_dev_fabr_usr (или задайте REMOTE_TAR_CMD от root в консоли Selectel).
set -euo pipefail

SSH_HOST="${SSH_HOST:-161.104.62.214}"
SSH_USER="${SSH_USER:-api_dev_fabr_usr}"
OUT_DIR="${OUT:-${HOME}/backups/fabrica-media}"
STAMP="$(date +%Y%m%d-%H%M)"
ARCHIVE="${OUT_DIR}/fabrica-uploads-${STAMP}.tar.gz"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=120 -o ServerAliveInterval=30 -o ServerAliveCountMax=10)
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

# Где искать медиа на сервере (первая существующая и непустая)
REMOTE_FIND='for p in /var/www/shared-fabrica/uploads \
  /var/www/api_dev_fabr_usr/data/shared/fabrica-uploads \
  /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web/uploads; do
  if [ -d "$p" ] && [ "$(find "$p" -type f 2>/dev/null | head -1 | wc -l)" -gt 0 ]; then
    echo "$p"; exit 0
  fi
done; exit 1'

if [ -n "${REMOTE_TAR_CMD:-}" ]; then
  REMOTE_PATH="custom"
else
  REMOTE_PATH="$(ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "$REMOTE_FIND")" || {
    echo "ERROR: SSH to ${SSH_USER}@${SSH_HOST} failed or media path not found." >&2
    echo "Add SSH key for ${SSH_USER} or run from Selectel console, then:" >&2
    echo "  REMOTE_TAR_CMD='tar cf - -C /var/www/... parent dir' OUT=~/backups $0" >&2
    exit 1
  }
fi

mkdir -p "$OUT_DIR"
echo "→ Source on server: ${REMOTE_PATH:-REMOTE_TAR_CMD}"
echo "→ Local archive: ${ARCHIVE} (~8+ GB, 10–40 min)"

if [ -n "${REMOTE_TAR_CMD:-}" ]; then
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "$REMOTE_TAR_CMD" | gzip -1 > "$ARCHIVE"
else
  PARENT="$(dirname "$REMOTE_PATH")"
  BASE="$(basename "$REMOTE_PATH")"
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "tar cf - -C '$(printf '%s' "$PARENT" | sed "s/'/'\\\\''/g")' '$(printf '%s' "$BASE" | sed "s/'/'\\\\''/g")'" \
    | gzip -1 > "$ARCHIVE"
fi

echo "→ Verifying..."
gzip -t "$ARCHIVE"
ls -lh "$ARCHIVE"
echo "✓ Backup saved: ${ARCHIVE}"
