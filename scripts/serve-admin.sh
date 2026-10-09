#!/usr/bin/env bash
# Локальная админка: встроенный PHP-сервер + router для статики.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PORT="${PORT:-8080}"
HOST="${HOST:-0.0.0.0}"

if ! php yii migrate/up --interactive=0 2>&1; then
  echo "Ошибка: не удалось подключиться к БД (config/db.php, MySQL запущен?)." >&2
  exit 1
fi

echo "Админка: http://127.0.0.1:${PORT}/admin/site/login"
if [[ "${HOST}" != "127.0.0.1" && "${HOST}" != "localhost" ]]; then
  echo "Слушает: http://${HOST}:${PORT}/ (в браузере используйте 127.0.0.1)"
fi
echo "Остановка: Ctrl+C"
exec php yii serve "${HOST}:${PORT}" -r web/router.php
