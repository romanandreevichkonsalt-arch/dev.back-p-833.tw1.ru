# Деплой back-dev — инструкция для агента

Dev: **https://dev.back-p-833.tw1.ru**  
SSH: `dev_back_p_8_usr@5.129.203.254` (тот же IP, что у `dev.back-p-833.tw1.ru`).

Скрипт: `./deploy/deploy.sh` из **корня репозитория**.  
Cursor-skill: `.cursor/skills/back-dev-deploy/SKILL.md` (краткая выжимка + когда какой режим).

## SSH на рабочей машине разработчика

На типичной локальной среде **нет** `~/.ssh/github_actions_deploy`.  
Ключ `~/.ssh/id_ed25519` **не** принимается пользователем `dev_back_p_8_usr` (BatchMode → `Permission denied`).

**Рабочий способ:** пароль через `SSH_ASKPASS` и локальный файл **`deploy/.ssh-askpass.sh`** (не в git, не в rsync).

### Один раз на машине

```bash
cp deploy/.ssh-askpass.sh.example deploy/.ssh-askpass.sh
# Вписать пароль SSH в .ssh-askpass.sh (строка exec echo '…')
chmod 700 deploy/.ssh-askpass.sh
```

Формат `deploy/.ssh-askpass.sh`:

```sh
#!/bin/sh
exec echo 'ВАШ_SSH_ПАРОЛЬ'
```

**Запрещено:** коммитить `.ssh-askpass.sh`, skill, правила Cursor, пароль в чат в командах shell (если файл уже есть — использовать только файл).

### Команда деплоя для агента

```bash
cd /home/vi/projects/back-dev
export SSH_ASKPASS="$PWD/deploy/.ssh-askpass.sh"
export SSH_ASKPASS_REQUIRE=force
export DISPLAY=:0
./deploy/deploy.sh files   # или app / full
```

Shell: `required_permissions: ["all"]` (SSH + rsync вне песочницы).

Если `deploy/.ssh-askpass.sh` **отсутствует** — попросить пользователя создать из example; не подставлять пароль из истории чата в скрипт/skill.

При **`rsync: Connection timed out`** после успешного `connected` — **повторить** ту же команду один раз (часто проходит со второй попытки).

Опционально (ключ вместо пароля): `deploy/setup-github-actions-key.sh` на сервере + `~/.ssh/github_actions_deploy` локально, затем обычный `./deploy/deploy.sh` без ASKPASS.

## Режимы

| Режим   | Команда                      | Когда |
|---------|------------------------------|--------|
| **files** | `./deploy/deploy.sh files` | PHP, views, JS, CSS, services — без миграций и без OpenAPI |
| **app**   | `./deploy/deploy.sh app`   | + `composer install`, `yii migrate/up` |
| **full**  | `./deploy/deploy.sh full`  | + `open-api/generate` — менялись `docs/OpenApiSpec.php` или `@OA` в API-контроллерах |

По умолчанию для правок админки/прomo/каталога без Swagger — **`files`**.  
После коммита с `docs/OpenApiSpec.php` — **`full`**.

## Workflow

1. **commit / push** — только по явной просьбе пользователя.
2. Выбрать режим по diff (см. таблицу).
3. Запустить деплой с ASKPASS (см. выше).
4. Дождаться строки: `✓ Деплой (...) завершён`.
5. Проверить:

```bash
curl -sI "https://dev.back-p-833.tw1.ru/api/v1/catalog/menu" | head -5
```

Для **full**:

```bash
curl -sS "https://dev.back-p-833.tw1.ru/swagger/json-schema" | head -c 200
```

## Что не уезжает на сервер

`vendor/`, `runtime/`, `web/assets/`, `web/uploads/`, `.env`, `config/db.php`, `deploy/.ssh-askpass.sh`.

## Переменные (обычно не нужны)

```bash
SSH_HOST=5.129.203.254
SSH_USER=dev_back_p_8_usr
SITE_DOMAIN=dev.back-p-833.tw1.ru
```

## GitHub Actions

`.github/workflows/deploy-prod.yml` — деплой с ключом в CI, режим **full** (отдельно от локального ASKPASS).
