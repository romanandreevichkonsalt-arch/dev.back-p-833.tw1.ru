#!/usr/bin/env bash
# Создаёт production .env (из staging + prod DB/URL) и кладёт на сервер.
# Требует SSH-ключ у api_mf_anna__usr (FastPanel → SSH-ключи).
#   ./deploy/push-production-env.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGING_ENV="/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/.env"
PROD_APP="/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru"
PROD_ENV="${PROD_APP}/.env"
LOCAL_ENV="$(mktemp)"
trap 'rm -f "$LOCAL_ENV"' EXIT

SSH_HOST="${SSH_HOST:-161.104.62.214}"
STAGING_USER="${STAGING_USER:-api_dev_fabr_usr}"
PROD_USER="${PROD_USER:-api_mf_anna__usr}"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)
if [ -n "${SSH_IDENTITY_FILE:-}" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "$SSH_IDENTITY_FILE")
elif [ -f "${HOME}/.ssh/id_ed25519" ]; then
  SSH_OPTS+=(-o IdentitiesOnly=yes -i "${HOME}/.ssh/id_ed25519")
fi

if [ -z "${PROD_DB_PASSWORD:-}" ] && [ -n "${PROD_DB_PASSWORD_FILE:-}" ] && [ -f "${PROD_DB_PASSWORD_FILE}" ]; then
  PROD_DB_PASSWORD="$(cat "${PROD_DB_PASSWORD_FILE}")"
  export PROD_DB_PASSWORD
fi
if [ -z "${PROD_DB_PASSWORD:-}" ]; then
  echo "ERROR: set PROD_DB_PASSWORD or PROD_DB_PASSWORD_FILE (api_mf_anna_ DB password)." >&2
  exit 1
fi

if ! ssh "${SSH_OPTS[@]}" -o BatchMode=yes "${PROD_USER}@${SSH_HOST}" 'echo ok' >/dev/null 2>&1; then
  echo "ERROR: SSH to ${PROD_USER}@${SSH_HOST} failed (publickey)." >&2
  echo "Add $(ssh-keygen -y -f "${SSH_IDENTITY_FILE:-${HOME}/.ssh/id_ed25519}" 2>/dev/null || echo '~/.ssh/id_ed25519.pub') in FastPanel for ${PROD_USER}." >&2
  exit 1
fi

echo "→ Read staging .env..."
ssh "${SSH_OPTS[@]}" "${STAGING_USER}@${SSH_HOST}" "cat '${STAGING_ENV}'" > "$LOCAL_ENV"

php -r '
$path = $argv[1];
$lines = file($path, FILE_IGNORE_NEW_LINES);
$out = [];
$vars = [];
foreach ($lines as $line) {
    if ($line === "" || $line[0] === "#") continue;
    $pos = strpos($line, "=");
    if ($pos === false) continue;
    $vars[substr($line, 0, $pos)] = substr($line, $pos + 1);
}
$vars["DB_DSN"] = "mysql:host=localhost;dbname=api_mf_anna_";
$vars["DB_USER"] = "api_mf_anna_";
unset($vars["DB_PASSWORD"]);
$vars["FRONTEND_URL"] = "https://mf-anna.ru";
$vars["YOOKASSA_RETURN_URL"] = "https://mf-anna.ru/cart?fromPayment=1";
$vars["YOOKASSA_RECEIPT_FALLBACK_EMAIL_DOMAIN"] = "noreply.mf-anna.ru";
unset($vars["CORS_ORIGINS"]);
$order = ["DB_DSN", "DB_USER", "DB_PASSWORD", "FRONTEND_URL", "CORS_ORIGINS", "YOOKASSA_SHOP_ID", "YOOKASSA_SECRET_KEY", "YOOKASSA_RETURN_URL", "YOOKASSA_RECEIPT_EMAIL", "YOOKASSA_RECEIPT_FALLBACK_EMAIL_DOMAIN", "DADATA_API_KEY", "DADATA_SECRET_KEY"];
$written = [];
$quoteVal = static function (string $key, string $val): string {
    if (($key === "DB_PASSWORD" || $key === "DB_USER") && preg_match("/[#\\s\"]/", $val)) {
        return "\"" . str_replace("\"", "\\\"", $val) . "\"";
    }
    return $val;
};
foreach ($order as $key) {
    if (!array_key_exists($key, $vars)) continue;
    $val = $vars[$key];
    if ($val === "") continue;
    $out[] = $key . "=" . $quoteVal($key, $val);
    $written[$key] = true;
}
foreach ($vars as $key => $val) {
    if (isset($written[$key]) || $val === "") continue;
    $out[] = $key . "=" . $quoteVal($key, $val);
}
file_put_contents($path, implode("\n", $out) . "\n");
' "$LOCAL_ENV"

if [ -n "${PROD_DB_PASSWORD:-}" ]; then
  php -r '
$path = $argv[1];
$pass = $argv[2];
$lines = file($path, FILE_IGNORE_NEW_LINES);
$found = false;
foreach ($lines as &$line) {
    if (str_starts_with($line, "DB_PASSWORD=")) {
        $line = "DB_PASSWORD=\"" . str_replace("\"", "\\\"", $pass) . "\"";
        $found = true;
    }
}
if (!$found) {
    $lines[] = "DB_PASSWORD=\"" . str_replace("\"", "\\\"", $pass) . "\"";
}
file_put_contents($path, implode("\n", $lines) . "\n");
' "$LOCAL_ENV" "$PROD_DB_PASSWORD"
fi

echo "→ Install ${PROD_ENV}..."
ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "mkdir -p '${PROD_APP}'"
scp "${SSH_OPTS[@]}" "$LOCAL_ENV" "${PROD_USER}@${SSH_HOST}:${PROD_ENV}"
ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "chmod 600 '${PROD_ENV}'"

if [ -f "${PROD_APP}/yii" ] || ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "test -f '${PROD_APP}/yii'"; then
  echo "→ sync db.php from .env..."
  ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "bash '${PROD_APP}/../repo/api.mf-anna.ru/deploy/deploy-backend.sh' api.mf-anna.ru files" 2>/dev/null \
    || ssh "${SSH_OPTS[@]}" "${PROD_USER}@${SSH_HOST}" "test -x '${PROD_APP}/deploy/deploy-backend.sh' && bash '${PROD_APP}/deploy/deploy-backend.sh' api.mf-anna.ru files" \
    || true
fi

echo "✓ ${PROD_ENV} installed"
