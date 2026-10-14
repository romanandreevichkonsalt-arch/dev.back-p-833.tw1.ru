# Деплой prod — api.dev-fabrika.ru

Prod: **https://api.dev-fabrika.ru**  
SSH: `api_dev_fabr_usr@161.104.62.214` (FastPanel, **только по SSH-ключу**).

Пароль из панели «Пользователь сайта» — для FTP/MySQL/phpMyAdmin, **не для SSH**.

## 1. SSH-ключ (один раз)

У сайта **нет** встроенного «Терминала» — команды и деплой идут **с вашего ПК** по SSH.

Ключ добавляют в панели (не у домена):

1. **Управление → Пользователи** (Users) → **`api_dev_fabr_usr`** → ⋮ → **SSH-ключи** → тот же ключ; включить **SSH-доступ** для этого пользователя.  
   ([инструкция FastPanel](https://kb.fastpanel.direct/ru/quick_start/how-to-set-up-ssh-keys/))
2. Ключ только в **«SSH Keys»** у `fastuser` (верхний правый угол) **не подходит** для деплоя: файлы сайта лежат у `api_dev_fabr_usr`, `fastuser` туда не зайдёт.

Публичный ключ разработчика: `~/.ssh/id_ed25519.pub`

На карточке сайта **«Доступ к сайту»** — логин SFTP (`api_dev_fabr_usr`) и пароль.

Проверка (должно быть `ok`, не `Permission denied`):

```bash
ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_dev_fabr_usr@161.104.62.214 'echo ok'
```

## 2. Дамп БД с dev

Файл `dev_back_p_8.sql` из загрузок — **не дамп**, а сохранённая HTML-страница phpMyAdmin.

Правильно: phpMyAdmin → БД → **Экспорт** → формат **SQL** → «Вперёд».

Или на dev-сервере: `mysqldump ... > dev.sql`

Импорт на prod (после bootstrap):

```bash
./deploy/import-db-remote.sh /path/to/real-dump.sql
```

## 3. Первый выклад (git на сервере)

Первый выклад **с локального ПК** (после пункта 1):

```bash
cd /path/to/fabrica-beget
# положить .env на сервер (см. .env.prod.local / .env.example)
./deploy/deploy-prod.sh full
./deploy/import-db-remote.sh /path/to/dump.sql   # если есть нормальный SQL
```

Bootstrap на сервере через «терминал сайта» в FastPanel **обычно недоступен** — не требуется, если есть SSH с ПК.

Если репозиторий ещё не клонирован — скрипт сам сделает `git clone` в  
`/var/www/api_dev_fabr_usr/data/repo/api.dev-fabrika.ru`.

**Либо** с локальной машины (после SSH-ключа):

```bash
./deploy/deploy-prod.sh full   # rsync + composer + migrate + openapi
```

и отдельно положить `.env` на сервер в  
`/var/www/api_dev_fabr_usr/data/app/api.dev-fabrika.ru/.env`,  
затем снова `./deploy/deploy-prod.sh files`.

## 4. Обновления

**С локальной машины** (как dev):

```bash
./deploy/deploy-prod.sh files   # | app | full
```

**На сервере через git:**

```bash
bash deploy/server-git-update.sh app
```

## Медиафайлы (uploads)

При деплое **`web/uploads/` не копируется** (исключён в rsync). После импорта БД:

```bash
./deploy/sync-uploads-remote.sh
```

Скачивает ~5k файлов с `https://dev.back-p-833.tw1.ru` по путям из `media_files` (лог: `runtime/sync-uploads.log` на сервере).

Быстрее (когда есть SSH к dev): `rsync -av dev_back_p_8_usr@200.165.224.88:.../web/uploads/ prod:.../web/uploads/`

## 5. Проверка

```bash
curl -sI "https://api.dev-fabrika.ru/api/v1/catalog/menu" | head -5
```

## Секреты

Не коммитить: `.env`, `config/db.php`, `deploy/.ssh-askpass-prod.sh`.
