# Устарело: «prod» = staging

Раньше этот файл описывал выкладку на **api.dev-fabrika.ru** как «prod». Сейчас:

| Среда | Документация | Скрипт |
|-------|----------------|--------|
| **Staging** (develop) | [AGENT-DEPLOY-STAGING.md](./AGENT-DEPLOY-STAGING.md) | `./deploy/deploy-staging.sh` |
| **Production** (main) | [AGENT-DEPLOY-PRODUCTION.md](./AGENT-DEPLOY-PRODUCTION.md) | `CONFIRM=1 ./deploy/deploy-production.sh` |

`./deploy/deploy-prod.sh` — алиас на `deploy-staging.sh`.
