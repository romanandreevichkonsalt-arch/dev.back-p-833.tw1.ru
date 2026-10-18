#!/usr/bin/env bash
# Одна медиатека для api.dev-fabrika.ru и api.mf-anna.ru (один VPS, FastPanel).
# Запуск из корня репозитория после SSH-ключей у обоих пользователей.
#
#   ./deploy/setup-shared-uploads.sh
#
# Каталог хранения (8.8G остаётся один раз):
#   /var/www/api_dev_fabr_usr/data/shared/fabrica-uploads
set -euo pipefail

SSH_HOST="${SSH_HOST:-161.104.62.214}"
DEV_USER="${DEV_USER:-api_dev_fabr_usr}"
PROD_USER="${PROD_USER:-api_mf_anna__usr}"
SHARED="/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads"
DEV_WEB="/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web"
PROD_WEB="/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/web"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=60)
if [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

dev() { ssh "${SSH_OPTS[@]}" "${DEV_USER}@${SSH_HOST}" "$@"; }
prod() { ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "$@"; }

echo "→ [dev] shared storage + symlink..."
dev bash -s <<EOF
set -euo pipefail
SHARED='${SHARED}'
DEV_WEB='${DEV_WEB}'
mkdir -p "\$(dirname "\$SHARED")"
if [ -d "\${DEV_WEB}/uploads" ] && [ ! -L "\${DEV_WEB}/uploads" ]; then
  if [ ! -d "\$SHARED" ] || [ -z "\$(ls -A "\$SHARED" 2>/dev/null)" ]; then
    mv "\${DEV_WEB}/uploads" "\$SHARED"
  else
    echo "WARN: \$SHARED already has data; merge manually if needed." >&2
  fi
elif [ ! -d "\$SHARED" ]; then
  mkdir -p "\$SHARED"
fi
ln -sfn "\$SHARED" "\${DEV_WEB}/uploads"
# fastsecure: оба пользователя сайта
for d in /var/www/api_dev_fabr_usr/data /var/www/api_dev_fabr_usr/data/app \
  /var/www/api_dev_fabr_usr/data/shared "\$SHARED"; do
  [ -e "\$d" ] || continue
  chgrp fastsecure "\$d" 2>/dev/null || true
  chmod g+rx "\$d" 2>/dev/null || true
done
chmod -R g+rwX "\$SHARED"
find "\$SHARED" -type d -exec chmod g+s {} + 2>/dev/null || true
chgrp -R fastsecure "\$SHARED" 2>/dev/null || true
du -sh "\$SHARED"
find "\$SHARED" -type f | wc -l
EOF

echo "→ [prod] symlink to shared..."
prod bash -s <<EOF
set -euo pipefail
SHARED='${SHARED}'
PROD_WEB='${PROD_WEB}'
mkdir -p "\${PROD_WEB}"
if [ -e "\${PROD_WEB}/uploads" ] && [ ! -L "\${PROD_WEB}/uploads" ]; then
  rm -rf "\${PROD_WEB}/uploads"
fi
ln -sfn "\$SHARED" "\${PROD_WEB}/uploads"
ls -la "\${PROD_WEB}/uploads"
test -r "\${SHARED}/." && echo "prod_read_ok" || echo "prod_read_FAIL"
find "\${SHARED}" -type f 2>/dev/null | wc -l
EOF

echo "✓ Shared uploads: \${SHARED}"
