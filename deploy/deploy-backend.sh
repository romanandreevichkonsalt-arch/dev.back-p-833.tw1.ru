#!/usr/bin/env bash
# FastPanel Yii2 deploy на сервере: repo/ → app/, symlink www → web/.
# Usage: deploy-backend.sh <domain> [files|app|full]
set -euo pipefail

DOMAIN="${1:?Usage: deploy-backend.sh <domain> [files|app|full]}"
MODE="${2:-full}"

case "$MODE" in
  files|app|full) ;;
  *)
    echo "ERROR: unknown mode «${MODE}». Use: files | app | full" >&2
    exit 1
    ;;
esac

USER_HOME="/var/www/$(whoami)/data"
WWW_ROOT="${USER_HOME}/www/${DOMAIN}"
APP_ROOT="${USER_HOME}/app/${DOMAIN}"
REPO_DIR="${USER_HOME}/repo/${DOMAIN}"

if [ ! -d "$REPO_DIR" ]; then
    echo "ERROR: ${REPO_DIR} not found. Run rsync from CI first."
    exit 1
fi

mkdir -p "$APP_ROOT"

echo "→ [${MODE}] repo → app..."
rsync -a --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='config/db.php' \
    --exclude='runtime/**' \
    --exclude='web/assets/**' \
    --exclude='web/uploads/**' \
    --exclude='vendor/' \
    "${REPO_DIR}/" "${APP_ROOT}/"

if [ ! -f "${APP_ROOT}/config/db.php" ] && [ -f "${REPO_DIR}/config/db.php" ]; then
    cp "${REPO_DIR}/config/db.php" "${APP_ROOT}/config/db.php"
fi

sync_db_config_from_env() {
    local app_root="$1"
    local env_file="${app_root}/.env"
    if [ ! -f "$env_file" ]; then
        return 0
    fi
    php -r '
$envFile = $argv[1];
$out = $argv[2];
$vars = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
    if ($line === "" || $line[0] === "#") continue;
    $pos = strpos($line, "=");
    if ($pos === false) continue;
    $vars[substr($line, 0, $pos)] = substr($line, $pos + 1);
}
$dsn = $vars["DB_DSN"] ?? "";
$user = $vars["DB_USER"] ?? "";
$pass = $vars["DB_PASSWORD"] ?? "";
if ($dsn === "" || $user === "") exit(0);
$cfg = "<?php\n\nreturn [\n    '\''class'\'' => '\''yii\\\\db\\\\Connection'\'',\n    '\''dsn'\'' => '\''" . addslashes($dsn) . "'\'',\n    '\''username'\'' => '\''" . addslashes($user) . "'\'',\n    '\''password'\'' => '\''" . addslashes($pass) . "'\'',\n    '\''charset'\'' => '\''utf8mb4'\'',\n];\n";
file_put_contents($out, $cfg);
' "$env_file" "${app_root}/config/db.php"
}

sync_db_config_from_env "$APP_ROOT"

apply_prod_env() {
    local file="$1"
    if [ -f "$file" ]; then
        sed -i "s/YII_DEBUG', true/YII_DEBUG', false/" "$file"
        sed -i "s/YII_ENV', 'dev/YII_ENV', 'prod/" "$file"
    fi
}

apply_prod_env "${APP_ROOT}/web/index.php"
apply_prod_env "${APP_ROOT}/yii"

if [ -d "${APP_ROOT}/web" ]; then
    if [ -L "$WWW_ROOT" ]; then
        ln -sfn "${APP_ROOT}/web" "$WWW_ROOT"
    elif [ ! -e "$WWW_ROOT" ]; then
        ln -sfn "${APP_ROOT}/web" "$WWW_ROOT"
    elif [ -d "$WWW_ROOT" ] && [ ! -L "$WWW_ROOT" ]; then
        if ! grep -q 'Yii' "${WWW_ROOT}/index.php" 2>/dev/null; then
            rm -rf "${WWW_ROOT:?}"
            ln -sfn "${APP_ROOT}/web" "$WWW_ROOT"
        fi
    fi
fi

mkdir -p "${APP_ROOT}/runtime" "${APP_ROOT}/web/assets" "${APP_ROOT}/web/uploads"
chmod -R 775 "${APP_ROOT}/runtime" "${APP_ROOT}/web/assets" 2>/dev/null || true

cd "$APP_ROOT"

if [ -f composer.json ]; then
    echo "→ composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction --no-ansi
fi

if [ "$MODE" = "files" ]; then
    echo "Backend deploy (${MODE}) completed for ${DOMAIN}"
    exit 0
fi

if [ -f yii ]; then
    echo "→ migrate/up..."
    php yii migrate/up --interactive=0
fi

if [ "$MODE" = "app" ]; then
    echo "Backend deploy (${MODE}) completed for ${DOMAIN}"
    exit 0
fi

if [ -f yii ]; then
    echo "→ open-api/generate..."
    php yii open-api/generate
fi

echo "Backend deploy (${MODE}) completed for ${DOMAIN} ($(git -C "$REPO_DIR" log -1 --oneline 2>/dev/null || echo 'no git'))"
