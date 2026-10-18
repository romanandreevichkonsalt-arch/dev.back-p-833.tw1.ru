#!/bin/bash
# Запускать на VPS от root (консоль Selectel), не с локального ПК.
# Копирует SSH-ключи с production-пользователя на staging (тот же ключ fabrica-tania).

set -euo pipefail

DEV=/var/www/api_dev_fabr_usr/data
PROD=/var/www/api_mf_anna__usr/data

if [[ "$(id -un)" != root ]]; then
  echo "Запустите от root: bash fix-staging-ssh-on-server.sh" >&2
  exit 1
fi

if [[ ! -f "$PROD/.ssh/authorized_keys" ]]; then
  echo "Нет файла $PROD/.ssh/authorized_keys" >&2
  exit 1
fi

mkdir -p "$DEV/.ssh"
rm -f "$DEV/.ssh"/authorizedK*
cp "$PROD/.ssh"/auth* "$DEV/.ssh"/
chown -R api_dev_fabr_usr:api_dev_fabr_usr "$DEV/.ssh"
chmod 700 "$DEV/.ssh"
chmod 600 "$DEV/.ssh"/auth*

echo "OK: staging authorized_keys скопирован с production."
wc -l "$DEV/.ssh/authorized_keys"
