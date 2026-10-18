# Перенос данных staging → production (один раз)

Оба сайта на **161.104.62.214**, разные FastPanel-пользователи.

## Предусловия

- [ ] SSH-ключ добавлен для `api_mf_anna__usr`
- [ ] На production создан `.env` (БД `api_mf_anna_`, `FRONTEND_URL=https://mf-anna.ru`, YooKassa **боевые**)
- [ ] SSL для `api.mf-anna.ru` работает

## Порядок

1. **Код на production** (ветка `main`, пустой или свежий `app/`):

   ```bash
   git checkout main
   CONFIRM=1 ./deploy/deploy-production.sh full
   ```

2. **База** (копия со staging):

   ```bash
   CONFIRM=1 ./deploy/copy-db-staging-to-production.sh
   ```

   Либо вручную: `./deploy/export-db-remote.sh` → `CONFIRM=1 ./deploy/import-db-production.sh dump.sql`

3. **Медиатека** (`web/uploads/` не входит в rsync деплоя):

   ```bash
   CONFIRM=1 ./deploy/copy-uploads-staging-to-production.sh
   ```

4. **Smoke**:

   ```bash
   curl -sI "https://api.mf-anna.ru/api/v1/catalog/menu" | head -5
   ```

5. **Фронт** [mf-anna.ru](https://mf-anna.ru): env сборки → `https://api.mf-anna.ru`

## После go-live

- `develop` + `./deploy/deploy-staging.sh` — только staging
- `main` + `CONFIRM=1 ./deploy/deploy-production.sh` — production
- Не заливать дамп staging на production без отдельной процедуры
