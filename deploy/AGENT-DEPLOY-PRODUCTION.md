# Деплой production — api.mf-anna.ru

Production (пользователи): **https://api.mf-anna.ru**  
Фронт: **https://mf-anna.ru** (`FRONTEND_URL` в `.env`)  
Ветка: **`main`**  
SSH: `api_mf_anna__usr@161.104.62.214` (ключ в FastPanel у этого пользователя).

## SSH-ключ (один раз)

```bash
ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_mf_anna__usr@161.104.62.214 'echo ok'
```

## `.env` на сервере

Файл: `/var/www/api_mf_anna__usr/data/app/api.mf-anna.ru/.env` (chmod 600).  
Не коммитить. См. `.env.example` (секция production): отдельная БД `api_mf_anna_`, боевые ключи YooKassa, `FRONTEND_URL=https://mf-anna.ru`.

Автоматически (ключ YooKassa и DaData копируются со staging):

```bash
PROD_DB_PASSWORD='…' ./deploy/push-production-env.sh
```

Пароль с `#` в `.env` только в кавычках: `DB_PASSWORD="…"`.

## Деплой с локальной машины

Только осознанно, с ветки `main`:

```bash
git checkout main
CONFIRM=1 ./deploy/deploy-production.sh app   # | files | full
```

## Пути на сервере

```
/var/www/api_mf_anna__usr/data/
├── repo/api.mf-anna.ru/
├── app/api.mf-anna.ru/
└── www/api.mf-anna.ru → app/.../web
```

## Импорт БД

```bash
CONFIRM=1 ./deploy/import-db-production.sh /path/to/dump.sql
```

## Первичный перенос со staging

На одном сервере (staging уже заполнен):

```bash
CONFIRM=1 ./deploy/copy-db-staging-to-production.sh
CONFIRM=1 ./deploy/copy-uploads-staging-to-production.sh
git checkout main
CONFIRM=1 ./deploy/deploy-production.sh full
```

Подробнее: [MIGRATE-STAGING-TO-PRODUCTION.md](./MIGRATE-STAGING-TO-PRODUCTION.md).

## Проверка

```bash
curl -sI "https://api.mf-anna.ru/api/v1/catalog/menu" | head -5
```

## CI (GitHub Actions)

Workflow **Deploy Production** — только `workflow_dispatch`, ветка `main`.  
Рекомендуется environment **`production`** с required reviewers (Settings → Environments).  
Secrets: `SSH_PRIVATE_KEY_PRODUCTION` или общий `SSH_PRIVATE_KEY` (pubkey должен быть у `api_mf_anna__usr`).

## Staging

Разработка: [AGENT-DEPLOY-STAGING.md](./AGENT-DEPLOY-STAGING.md).
