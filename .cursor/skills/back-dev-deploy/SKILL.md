---
name: back-dev-deploy
description: >-
  Deploy this repo to https://api.dev-fabrika.ru via deploy-prod.sh (files/app/full).
  Use when the user asks to deploy, push to dev, rsync, or update the server.
  Prefer mode files for PHP-only changes; app when migrations; full when OpenAPI changed.
---

# Деплой api.dev-fabrika.ru (по умолчанию для fabrica-beget)

**Полная инструкция:** [deploy/AGENT-DEPLOY-PROD.md](../../../deploy/AGENT-DEPLOY-PROD.md)

Цель: **https://api.dev-fabrika.ru** · SSH: `api_dev_fabr_usr@161.104.62.214`

Старый хост `dev.back-p-833.tw1.ru` — только если пользователь явно попросит; иначе **не использовать**.

## Команда

```bash
cd /path/to/fabrica-beget
./deploy/deploy-prod.sh files   # | app | full
```

`deploy-prod.sh` выставляет `SSH_HOST`, `SSH_USER`, `SITE_DOMAIN` и вызывает `deploy/deploy.sh`.

SSH-ключ: по умолчанию `~/.ssh/id_ed25519` (см. prod-док). BatchMode `Permission denied` → ключ не добавлен у `api_dev_fabr_usr` в FastPanel.

Shell: `required_permissions: ["all"]`.

Rsync timeout → **повторить** деплой один раз.

## Режимы

| Режим | Когда |
|-------|--------|
| **files** | Код без миграций и OpenAPI (default) |
| **app** | `migrations/`, `composer.lock` |
| **full** | `docs/OpenApiSpec.php`, `@OA` в API controllers |

## После деплоя

```bash
curl -sI "https://api.dev-fabrika.ru/api/v1/catalog/menu" | head -5
```

## Прочее

- Пути на сервере: [reference.md](reference.md)
- Legacy dev.back-p-833: [deploy/AGENT-DEPLOY.md](../../../deploy/AGENT-DEPLOY.md)
- Commit/push — только по запросу пользователя
