---
name: back-dev-deploy
description: >-
  Deploy back-dev to dev.back-p-833.tw1.ru with modes files/app/full.
  Use when the user asks to deploy, push to dev, rsync, or update the dev server.
  Prefer mode files for PHP-only changes; full only when OpenAPI or docs/controllers changed.
---

# Деплой back-dev

**Полная инструкция (SSH, ASKPASS, retry):** [deploy/AGENT-DEPLOY.md](../../../deploy/AGENT-DEPLOY.md) — читать перед деплоем.

Dev: **https://dev.back-p-833.tw1.ru** · SSH: `dev_back_p_8_usr@5.129.203.254`

## SSH (локально)

1. Файл **`deploy/.ssh-askpass.sh`** (из `deploy/.ssh-askpass.sh.example`, `chmod 700`, **не в git**).
2. Деплой **только так** (если нет `~/.ssh/github_actions_deploy`):

```bash
cd /path/to/back-dev
export SSH_ASKPASS="$PWD/deploy/.ssh-askpass.sh"
export SSH_ASKPASS_REQUIRE=force
export DISPLAY=:0
./deploy/deploy.sh files   # | app | full
```

3. Shell: `required_permissions: ["all"]`.
4. Нет `.ssh-askpass.sh` → попросить пользователя создать; **не** писать пароль в skill/коммит/команду из чата.
5. Rsync timeout → **повторить** деплой один раз.

`~/.ssh/id_ed25519` для `dev_back_p_8_usr` **не подходит**. Ключ CI: `~/.ssh/github_actions_deploy` — если есть, можно `./deploy/deploy.sh` без ASKPASS.

## Режимы

| Режим | Когда |
|-------|--------|
| **files** | Код без миграций и OpenAPI (default) |
| **app** | `migrations/`, `composer.lock` |
| **full** | `docs/OpenApiSpec.php`, `@OA` в API controllers |

## После деплоя

```bash
curl -sI "https://dev.back-p-833.tw1.ru/api/v1/catalog/menu" | head -5
```

## Прочее

- Архитектура repo/app: [reference.md](reference.md)
- Commit/push — только по запросу пользователя
