# Деплой staging — api.dev-fabrika.ru

Staging (разработка и QA): **https://api.dev-fabrika.ru**  
Ветка: **`develop`** (если ещё нет на origin: `git checkout -b develop && git push -u origin develop`)  
SSH: `api_dev_fabr_usr@161.104.62.214` (FastPanel, **только по SSH-ключу**).

Пароль из панели «Пользователь сайта» — для FTP/MySQL/phpMyAdmin, **не для SSH**.

## SSH-ключ (один раз)

1. **Управление → Пользователи** → **`api_dev_fabr_usr`** → SSH-ключи → тот же ключ, что у разработчика; включить SSH-доступ.
2. Проверка:

```bash
ssh -o IdentitiesOnly=yes -i ~/.ssh/id_ed25519 api_dev_fabr_usr@161.104.62.214 'echo ok'
```

## Деплой с локальной машины

```bash
git checkout develop
./deploy/deploy-staging.sh files   # | app | full
```

`deploy-prod.sh` — устаревший алиас на `deploy-staging.sh`.

## Пути на сервере

```
/var/www/api_dev_fabr_usr/data/
├── repo/api.dev-fabrika.ru/
├── app/api.dev-fabrika.ru/    ← .env, runtime, web/uploads
└── www/api.dev-fabrika.ru → app/.../web
```

`.env` на сервере: `app/api.dev-fabrika.ru/.env` (chmod 600), шаблон — `.env.example` (секция staging).

**YooKassa (staging):** магазин **1470173**, secret **`test_…`**, `YOOKASSA_RECEIPT_EMAIL=tania22gerasimenko@gmail.com` (все чеки на заглушку), `YOOKASSA_SEND_RECEIPT=1`. На production заглушку **не** задавать — чек на email из корзины.

## Импорт / экспорт БД

```bash
./deploy/export-db-remote.sh > staging-dump.sql   # по умолчанию staging
./deploy/import-db-staging.sh /path/to/dump.sql
```

## Проверка

```bash
curl -sI "https://api.dev-fabrika.ru/api/v1/catalog/menu" | head -5
```

## Production

Боевой API — **https://api.mf-anna.ru**, см. [AGENT-DEPLOY-PRODUCTION.md](./AGENT-DEPLOY-PRODUCTION.md).

CI: push в **`develop`** → `.github/workflows/deploy-staging.yml` (secret `SSH_PRIVATE_KEY_STAGING` или `SSH_PRIVATE_KEY`).

Legacy dev.back-p-833: [AGENT-DEPLOY.md](./AGENT-DEPLOY.md).
