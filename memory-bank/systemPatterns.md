# System Patterns

## Архитектура

```
controllers/
  api/v1/          — REST API (stateless, Bearer auth)
    ApiController  — базовый класс (CORS, JSON, HttpBearerAuth)
    AuthController — SMS + Yandex login
    ProfileController
    PingController
    OptionsController — CORS preflight
  SiteController   — веб-часть (сессии)
  SwaggerController — OpenAPI JSON schema
modules/
  admin/           — админка (сессионная авторизация)
models/            — ActiveRecord (User, SmsCode, ApiAccessToken, ExternalIdentity, UserProfile)
services/          — интерфейсы + реализации (DI через Yii container)
docs/              — OpenApiSpec (схемы и метаданные)
migrations/        — миграции БД
```

## Паттерны

### Разделение API и Web
- `ApiController::beforeAction()` отключает сессии (`enableSession = false`)
- Веб-часть (`SiteController`, `admin`) использует стандартные Yii2-сессии и cookies

### Аутентификация API
- `HttpBearerAuth` на базовом `ApiController`
- Токены хранятся как SHA-256 хеш в `api_access_tokens`
- `User::findIdentityByAccessToken()` — поиск по хешу с проверкой `expires_at` и `revoked_at`
- Legacy fallback: hardcoded users (admin/demo) для совместимости с шаблоном

### Dependency Injection
- `SmsSenderInterface` → `SmsSenderStub` (singleton)
- `YandexIdServiceInterface` → `YandexIdService` (factory с params)

### OpenAPI
- Аннотации `@OA\` в контроллерах + схемы в `docs/OpenApiSpec.php`
- Генерация: `GET /swagger/json-schema` через `OpenApi\Generator`

### CORS
- `Cors` filter в `ApiController` и `OptionsController`
- Preflight: `OPTIONS api/v1/<path>` → `OptionsController::actionPreflight`

## Схема БД

| Таблица | Назначение |
|---------|-----------|
| `users` | phone (unique), username |
| `sms_codes` | коды подтверждения (expires_at, used_at) |
| `api_access_tokens` | Bearer-токены (token_hash, expires_at, revoked_at) |
| `external_identities` | привязка к OAuth-провайдерам (yandex) |
| `user_profiles` | имя, email, avatar, yandex_login |

## Соглашения
- Namespace: `app\`
- API routes в `config/web.php` → `urlManager.rules`
- Миграции: `mYYMMDD_HHMMSS_description.php`
- Ответы контроллеров — массивы (автоматически в JSON)
