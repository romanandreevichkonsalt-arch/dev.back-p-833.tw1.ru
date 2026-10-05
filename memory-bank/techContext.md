# Tech Context

## Стек
| Компонент | Версия/пакет |
|-----------|-------------|
| PHP | >= 7.4 |
| Framework | Yii2 ~2.0.45 |
| UI (web) | yii2-bootstrap5 |
| Mail | yii2-symfonymailer |
| OpenAPI | zircote/swagger-php ^6.0 |
| Annotations | doctrine/annotations ^2.0 |
| DB | MySQL (yii\db\Connection) |
| Tests | Codeception ^5.0 |

## Dev-инструменты
- yii2-debug, yii2-gii (только YII_ENV_DEV)
- Docker: `yiisoftware/yii2-php:7.4-apache`, порт `8000`
- Vagrant (опционально)

## Конфигурация
- `config/web.php` — web-приложение, urlManager, DI container
- `config/console.php` — консоль (миграции)
- `config/params.php` — yandexId.userinfoUrl, email-настройки
- `config/db.php` — подключение к MySQL (не в git, есть `_db.php` как шаблон)
- `config/test.php`, `config/test_db.php` — тестовое окружение

## API Endpoints (v1)

| Method | Path | Auth | Описание |
|--------|------|------|----------|
| POST | `/api/v1/auth/request-code` | — | Запрос SMS-кода |
| POST | `/api/v1/auth/verify-code` | — | Проверка кода → Bearer |
| POST | `/api/v1/auth/yandex` | — | Яндекс ID → Bearer |
| GET | `/api/v1/ping` | — | Health check |
| GET | `/api/v1/profile/me` | Bearer | Профиль пользователя |
| POST | `/api/v1/favorites/add` | Bearer или X-Session-ID | Добавить в избранное |
| POST | `/api/v1/favorites/remove` | Bearer или X-Session-ID | Удалить из избранного |
| POST | `/api/v1/favorites/check` | Bearer или X-Session-ID | Map slug → isFavorite |
| GET | `/api/v1/favorites/list` | Bearer или X-Session-ID | Список избранного |
| POST | `/api/v1/favorites/sync` | Bearer + session | Merge гостевого избранного |
| OPTIONS | `/api/v1/*` | — | CORS preflight |
| GET | `/swagger/json-schema` | — | OpenAPI JSON |

Каталог `sort=default`: RR по `model_id`, цена↑ внутри модели, приоритеты из `/admin/search?tab=catalog` (scope direction). Поиск: RR по линейкам A→Z × цена + те же приоритеты. Кастом-SKU не в витрине.

## Запуск

```bash
# Docker
docker-compose up -d
# → http://127.0.0.1:8000

# Миграции
php yii migrate

# Тесты
vendor/bin/codecept run
```

## Известные ограничения
- `SmsSenderStub` — SMS не отправляются реально, код логируется и возвращается в ответе
- `request-code` возвращает код в теле ответа (только для dev)
- Legacy users (admin/demo) остались в `User::$users` для веб-логина
