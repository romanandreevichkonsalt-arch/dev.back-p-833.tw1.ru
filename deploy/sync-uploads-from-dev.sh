#!/usr/bin/env bash
# Скачивает web/uploads с dev (HTTPS) на prod по путям из media_files.
# Запуск на prod под api_dev_fabr_usr или локально через SSH (см. deploy/sync-uploads-remote.sh).
set -euo pipefail

DEV_BASE="${DEV_BASE:-https://dev.back-p-833.tw1.ru}"
APP_ROOT="${APP_ROOT:-/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru}"
WEB_ROOT="${WEB_ROOT:-${APP_ROOT}/web}"
ENV_FILE="${ENV_FILE:-${APP_ROOT}/.env}"
PARALLEL="${PARALLEL:-12}"
LOG="${LOG:-${APP_ROOT}/runtime/sync-uploads.log}"

if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: missing $ENV_FILE" >&2
  exit 1
fi
eval "$(php -r '
$path = $argv[1] ?? "";
if ($path === "" || !is_file($path)) { fwrite(STDERR, "bad env file\n"); exit(1); }
$vars = [];
foreach (file($path) as $line) {
    $line = trim($line);
    if ($line === "" || $line[0] === "#" || !str_contains($line, "=")) continue;
    [$k, $v] = explode("=", $line, 2);
    $vars[trim($k)] = trim($v);
}
foreach (["DB_DSN","DB_USER","DB_PASSWORD"] as $k) {
    if (empty($vars[$k])) { fwrite(STDERR, "missing $k\n"); exit(1); }
    echo "export " . $k . "=" . escapeshellarg($vars[$k]) . ";\n";
}
' "$ENV_FILE")"
DB_DSN="${DB_DSN:?DB_DSN required}"
DB_USER="${DB_USER:?}"
DB_PASSWORD="${DB_PASSWORD:?}"
DB_NAME="$(echo "$DB_DSN" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')"

mkdir -p "${WEB_ROOT}/uploads" "$(dirname "$LOG")"
LIST="$(mktemp)"
trap 'rm -f "$LIST"' EXIT

mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -N -e "
SELECT DISTINCT p FROM (
  SELECT path AS p FROM media_files WHERE path != ''
  UNION SELECT path_large FROM media_files WHERE path_large IS NOT NULL AND path_large != ''
  UNION SELECT path_medium FROM media_files WHERE path_medium IS NOT NULL AND path_medium != ''
  UNION SELECT path_mini FROM media_files WHERE path_mini IS NOT NULL AND path_mini != ''
  UNION SELECT path_listing_medium FROM media_files WHERE path_listing_medium IS NOT NULL AND path_listing_medium != ''
  UNION SELECT path_listing_mini FROM media_files WHERE path_listing_mini IS NOT NULL AND path_listing_mini != ''
) t ORDER BY p;
" > "$LIST"

TOTAL=$(wc -l < "$LIST")
echo "→ Sync $TOTAL files from $DEV_BASE → $WEB_ROOT" | tee -a "$LOG"

export DEV_BASE WEB_ROOT LOG
ok=0
fail=0

download_one() {
  local rel="$1"
  local dest="${WEB_ROOT}/${rel}"
  local url="${DEV_BASE}/${rel}"
  if [ -f "$dest" ] && [ -s "$dest" ]; then
    return 0
  fi
  mkdir -p "$(dirname "$dest")"
  if curl -fsS --connect-timeout 30 --max-time 120 "$url" -o "$dest"; then
    echo "OK $rel" >> "$LOG"
  else
    echo "FAIL $rel" >> "$LOG"
    rm -f "$dest"
    return 1
  fi
}
export -f download_one

if command -v xargs >/dev/null 2>&1; then
  xargs -P "$PARALLEL" -I{} bash -c 'download_one "$@"' _ {} < "$LIST" || true
else
  while read -r rel; do download_one "$rel" || true; done < "$LIST"
fi

fail=$(grep -c '^FAIL ' "$LOG" 2>/dev/null || true)
ok=$((TOTAL - fail))
echo "→ Done: ok≈$ok fail=$fail (see $LOG)" | tee -a "$LOG"
