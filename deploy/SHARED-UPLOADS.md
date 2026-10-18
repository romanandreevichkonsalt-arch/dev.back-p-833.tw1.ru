# Общая медиатека (staging + production API)

На одном VPS два сайта FastPanel = **два Linux-пользователя**. Симлинк из `api_mf_anna__usr` в каталог `api_dev_fabr_usr` **не работает**: доступ между домашними каталогами сайтов закрыт (даже с группой `fastsecure`).

### Рабочий вариант (нужен root / поддержка FastPanel)

Создать каталог **вне** `data/` обоих пользователей, например:

```
/var/www/shared-fabrica/uploads/   # владелец root, группа fastsecure, chmod 2775
```

Оба сайта: `web/uploads` → symlink на этот путь. Медиа (8.8G) один раз перенести туда с staging.

**Текст в поддержку (FastPanel / Selectel):**

> На VPS 161.104.62.214 нужен общий каталог для двух сайтов API:  
> `/var/www/shared-fabrica/uploads` — `root:fastsecure`, `2775`.  
> Доступ на запись: `api_dev_fabr_usr`, `api_mf_anna__usr`.

### Staging (уже сделано на dev-сервере)

Медиа лежит в:

```
/var/www/api_dev_fabr_usr/data/shared/fabrica-uploads/
```

`web/uploads` staging — symlink на этот каталог (одна копия для dev API).

Production **пока** с локальным пустым `web/uploads/` — полный каталог после расширения диска **или** после общего `/var/www/shared-fabrica/`.

## Перенос медиа в `/var/www/shared-fabrica/uploads` (root, VPS)

После **полного** локального бэкапа (~8.8G, ~6648 файлов):

```bash
# На VPS от root (консоль Selectel), скопировать скрипт из репо или вставить содержимое:
bash migrate-uploads-to-shared-fabrica.sh          # dry run
CONFIRM=1 bash migrate-uploads-to-shared-fabrica.sh
```

Файл в репозитории: `deploy/migrate-uploads-to-shared-fabrica.sh` — `mv` из `data/shared/fabrica-uploads`, symlinks на staging и production.

Legacy (только symlink staging → `data/shared/`, prod не видит файлы):

```bash
./deploy/setup-shared-uploads.sh
```

Проверка с production:

```bash
ssh api_mf_anna__usr@161.104.62.214 'find /var/www/api_dev_fabr_usr/data/shared/fabrica-uploads -type f | wc -l'
```

## Важно

- **Не** запускать `copy-uploads-staging-to-production.sh` — дубликат не нужен.
- Загрузки с staging и prod пишут в **один** каталог; тестовые файлы на staging видны на prod.
- Бэкап: один каталог `shared/fabrica-uploads`.
- Деплой по-прежнему **не** rsync-ит `web/uploads/` (симлинк сохраняется).

## Локальный бэкап медиатеки

С вашего ПК (нужен SSH `api_dev_fabr_usr`, ~10 GB на диске):

```bash
./deploy/backup-media-to-local.sh
# архив: ~/backups/fabrica-media/fabrica-uploads-YYYYMMDD-HHMM.tar.gz
```

Из консоли **root** Selectel (если staging SSH недоступен) — сначала найти каталог:

```bash
du -sh /var/www/shared-fabrica/uploads /var/www/api_dev_fabr_usr/data/shared/fabrica-uploads
```

## Откат

Под каждым пользователем вернуть локальный каталог `uploads/` и убрать symlink (редко нужно).
