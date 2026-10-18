# Общая медиатека на VPS (root / FastPanel)

Сервер **161.104.62.214**, два API: staging (`api_dev_fabr_usr`) и production (`api_mf_anna__usr`).

**Цель:** одна папка `/var/www/shared-fabrica/uploads` (~8.8G, 6648 файлов), оба сайта: `web/uploads` → symlink.

**Бэкап перед переносом:** локально полная копия (у вас: `/home/vi/Документы/fabrica-uploads`, 6648 файлов).

Команды ниже — **консоль root на VPS** (Selectel → сервер → консоль). Файловый менеджер FastPanel в `data/` **не** видит `/var/www/shared-fabrica`.

---

## Шаг 0. Войти как root

```bash
whoami   # root
df -h /var/www
```

Нужно **≥ ~10 GB свободно** на том же разделе, что `/var/www`, если `mv` пойдёт между разными дисками (при нехватке места — сначала расширить диск в Selectel).

---

## Шаг 1. Создать каталог для общей медиатеки

```bash
mkdir -p /var/www/shared-fabrica/uploads
chown root:fastsecure /var/www/shared-fabrica
chown root:fastsecure /var/www/shared-fabrica/uploads
chmod 755 /var/www/shared-fabrica
chmod 2775 /var/www/shared-fabrica/uploads
ls -la /var/www/shared-fabrica/
```

Ожидаемо: `uploads` — `drwxrwsr-x root fastsecure`.

**Если каталог уже есть** (пустой) — шаг 1 можно только проверить:

```bash
ls -la /var/www/shared-fabrica/uploads
find /var/www/shared-fabrica/uploads -type f | wc -l   # должно быть 0 перед переносом
```

---

## Шаг 2. Проверить, что оба пользователя сайта в группе fastsecure

```bash
id api_dev_fabr_usr
id api_mf_anna__usr
```

В строке `groups=` должна быть **`fastsecure`**. Если нет — в FastPanel или:

```bash
usermod -aG fastsecure api_dev_fabr_usr
usermod -aG fastsecure api_mf_anna__usr
```

(новая SSH-сессия пользователя подхватит группу.)

---

## Шаг 3. Сверка источника (medиа пока на staging)

```bash
SRC=/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads
du -sh "$SRC"
find "$SRC" -type f | wc -l
```

Ожидаемо: **~8.8G**, **6648** файлов.

---

## Шаг 4. Перенос файлов и симлинки

```bash
SRC=/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads
DST=/var/www/shared-fabrica/uploads
DEV_WEB=/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web
PROD_WEB=/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/web
SHARED=/var/www/shared-fabrica/uploads

shopt -s dotglob nullglob
mv "${SRC}"/* "${DST}"/
shopt -u dotglob nullglob
rmdir "${SRC}" 2>/dev/null || true

chgrp -R fastsecure /var/www/shared-fabrica/uploads
find /var/www/shared-fabrica/uploads -type d -exec chmod 2775 {} +
find /var/www/shared-fabrica/uploads -type f -exec chmod 664 {} +

rm -f "${DEV_WEB}/uploads"
rmdir "${PROD_WEB}/uploads" 2>/dev/null || rm -rf "${PROD_WEB}/uploads"
# prod: только если uploads пустой; иначе не удалять — проверьте ls -la

ln -sfn "${SHARED}" "${DEV_WEB}/uploads"
ln -sfn "${SHARED}" "${PROD_WEB}/uploads"

find /var/www/shared-fabrica/uploads -type f | wc -l
du -sh /var/www/shared-fabrica/uploads
ls -la "${DEV_WEB}/uploads" "${PROD_WEB}/uploads"
```

После шага: **6648** файлов, оба `uploads` → симлинк на `/var/www/shared-fabrica/uploads`.

**Альтернатива:** скрипт из репозитория на VPS (`deploy/migrate-uploads-to-shared-fabrica.sh`): dry run, затем `CONFIRM=1 bash …`.

---

## Шаг 5. Проверка с локального ПК

```bash
ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_dev_fabr_usr@161.104.62.214 \
  'readlink -f /var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/web/uploads; find /var/www/shared-fabrica/uploads -type f | wc -l'

ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_mf_anna__usr@161.104.62.214 \
  'readlink -f /var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/web/uploads; find /var/www/shared-fabrica/uploads -type f | wc -l'
```

Оба: путь **`/var/www/shared-fabrica/uploads`**, счётчик **6648**.

Открыть в браузере картинку с staging/prod API (URL из `/uploads/media/...`).

---

## Не делать

- Не запускать `copy-uploads-staging-to-production.sh` (дубликат 8G).
- Не удалять медиа на сервере без проверенного локального бэкапа.
- Не делать `rsync` staging→prod для медиа, если уже общий каталог.

## Откат (редко)

Под root: убрать symlinks, вернуть локальные `uploads/`, при необходимости `mv` обратно в `data/shared/fabrica-uploads` из бэкапа.
