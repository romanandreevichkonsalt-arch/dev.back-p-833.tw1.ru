---
name: back-dev-deploy
description: >-
  Deploy fabrica-beget: staging (develop → api.dev-fabrika.ru) or production
  (main → api.mf-anna.ru). Use deploy-staging.sh by default; production only with CONFIRM=1.
---

# Деплой API (fabrica-beget)

| Среда | URL | Ветка | Скрипт |
|-------|-----|-------|--------|
| Staging | https://api.dev-fabrika.ru | `develop` | `./deploy/deploy-staging.sh` |
| Production | https://api.mf-anna.ru | `main` | `CONFIRM=1 ./deploy/deploy-production.sh` |

**Staging:** [deploy/AGENT-DEPLOY-STAGING.md](../../../deploy/AGENT-DEPLOY-STAGING.md)  
**Production:** [deploy/AGENT-DEPLOY-PRODUCTION.md](../../../deploy/AGENT-DEPLOY-PRODUCTION.md)  
**Перенос данных:** [deploy/MIGRATE-STAGING-TO-PRODUCTION.md](../../../deploy/MIGRATE-STAGING-TO-PRODUCTION.md)

По умолчанию для агента — **только staging**, не production.

## Staging (default)

```bash
git checkout develop
./deploy/deploy-staging.sh files   # | app | full
```

SSH: `api_dev_fabr_usr@161.104.62.214`, ключ `~/.ssh/id_ed25519`.

## Production (explicit only)

```bash
git checkout main
CONFIRM=1 ./deploy/deploy-production.sh app
```

SSH: `api_mf_anna__usr@161.104.62.214`.

## Режимы

| Режим | Когда |
|-------|--------|
| **files** | Код без миграций и OpenAPI |
| **app** | `migrations/`, `composer.lock` |
| **full** | OpenAPI / `@OA` |

## После деплоя

```bash
curl -sI "https://api.dev-fabrika.ru/api/v1/catalog/menu" | head -5
curl -sI "https://api.mf-anna.ru/api/v1/catalog/menu" | head -5
```

Shell: `required_permissions: ["all"]`. Commit/push — только по запросу пользователя.
