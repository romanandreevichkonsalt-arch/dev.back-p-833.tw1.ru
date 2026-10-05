#!/usr/bin/env bash
# Полный деплoy (алиас deploy.sh full). См. deploy/deploy.sh для других режимов.
exec "$(cd "$(dirname "$0")" && pwd)/deploy.sh" full "$@"
