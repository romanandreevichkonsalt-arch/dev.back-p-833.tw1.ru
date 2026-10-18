#!/usr/bin/env bash
# Перенос медиатеки в /var/www/shared-fabrica/uploads и symlinks staging + production.
# Запуск на VPS от root (консоль Selectel):
#
#   bash migrate-uploads-to-shared-fabrica.sh
#   CONFIRM=1 bash migrate-uploads-to-shared-fabrica.sh
#
# Перед запуском: локальный бэкап ~8.8G / 6648 файлов.
set -euo pipefail

SRC=/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads
DST=/var/www/shared-fabrica/uploads
DEV_WEB=/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web
PROD_WEB=/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/web
SHARED=/var/www/shared-fabrica/uploads

if [[ "$(id -un)" != root ]]; then
  echo "ERROR: run as root on the VPS." >&2
  exit 1
fi

if [[ "${CONFIRM:-}" != 1 ]]; then
  echo "Dry run (counts and disk). Set CONFIRM=1 to migrate."
  df -h /var/www
  echo "SRC: $(du -sh "$SRC" 2>/dev/null || echo missing)"
  echo "DST: $(du -sh "$DST" 2>/dev/null || echo missing)"
  SRC_N="$(find "$SRC" -type f 2>/dev/null | wc -l)"
  DST_N="$(find "$DST" -type f 2>/dev/null | wc -l)"
  echo "Files SRC=$SRC_N DST=$DST_N"
  ls -la "${DEV_WEB}/uploads" "${PROD_WEB}/uploads" 2>/dev/null || true
  exit 0
fi

AVAIL_KB="$(df -k /var/www | awk 'NR==2 {print $4}')"
NEED_KB="$(du -sk "$SRC" | awk '{print $1}')"
# Если mv между разными FS, нужно место под копию; если один FS — хватит запаса ~512M
if (( AVAIL_KB < NEED_KB + 524288 )); then
  echo "ERROR: not enough free space on /var/www (${AVAIL_KB}K avail, src ${NEED_KB}K)." >&2
  df -h /var/www
  exit 1
fi

SRC_N="$(find "$SRC" -type f | wc -l)"
if (( SRC_N < 1 )); then
  echo "ERROR: SRC empty or missing." >&2
  exit 1
fi

mkdir -p "$DST"
DST_N="$(find "$DST" -type f 2>/dev/null | wc -l)"
if (( DST_N > 0 )); then
  echo "ERROR: DST already has $DST_N files. Merge or empty DST first." >&2
  exit 1
fi

echo "→ Moving $SRC_N files to $DST ..."
shopt -s dotglob nullglob
mv "$SRC"/* "$DST"/
shopt -u dotglob nullglob
rmdir "$SRC" 2>/dev/null || true

DST_N="$(find "$DST" -type f | wc -l)"
if (( DST_N != SRC_N )); then
  echo "ERROR: file count mismatch after mv (src was $SRC_N, dst now $DST_N)." >&2
  exit 1
fi

chgrp -R fastsecure "$SHARED"
find "$SHARED" -type d -exec chmod 2775 {} +
find "$SHARED" -type f -exec chmod 664 {} +

for web in "$DEV_WEB" "$PROD_WEB"; do
  up="${web}/uploads"
  if [[ -L "$up" ]]; then
    rm -f "$up"
  elif [[ -d "$up" ]]; then
    if [[ -n "$(ls -A "$up" 2>/dev/null)" ]]; then
      echo "ERROR: $up is a non-empty directory; not removing." >&2
      exit 1
    fi
    rmdir "$up"
  fi
  ln -sfn "$SHARED" "$up"
done

# Traverse path for both site users
chmod o+x /var/www/shared-fabrica 2>/dev/null || true

echo "→ Done. du -sh $SHARED:"
du -sh "$SHARED"
echo "Files: $DST_N"
ls -la "${DEV_WEB}/uploads" "${PROD_WEB}/uploads"
