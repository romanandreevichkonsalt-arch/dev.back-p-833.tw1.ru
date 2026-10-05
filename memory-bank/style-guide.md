# Style Guide

## Язык кода
- PHP (Yii2) — backend
- JavaScript — только при явном запросе

## PHP-конвенции (проект)

### Namespace и структура
- `app\controllers\api\v1\` — API-контроллеры
- `app\models\` — ActiveRecord
- `app\services\` — бизнес-логика через интерфейсы
- `app\docs\` — OpenAPI-схемы

### Контроллеры API
- Наследуют `ApiController`
- Возвращают `array` (JSON через ContentNegotiator)
- HTTP-ошибки: `BadRequestHttpException` (400), `UnauthorizedHttpException` (401)
- OpenAPI-аннотации `@OA\` на каждом action

### Модели
- `ActiveRecord` + `rules()`, `behaviors()` (TimestampBehavior)
- Таблицы: `{{%table_name}}`
- Валидация телефона: `/^7\d{10}$/`

### Сервисы
- Интерфейс + реализация
- Регистрация в `config/web.php` → `container.singletons`

### Миграции
- Именование: `mYYMMDD_HHMMSS_description.php`
- Методы: `safeUp()` / `safeDown()`
- FK с CASCADE

## Сообщения и тексты
- API-сообщения об ошибках — на русском
- OpenAPI tags/summary — на русском
- Название API: «Fabrika API»

## Git
- Короткие commit messages на английском (как в истории репозитория)

## Принципы разработки
- Минимальный scope изменений
- Следовать существующим паттернам проекта
- Не over-engineer
- Комментарии только для неочевидной бизнес-логики
