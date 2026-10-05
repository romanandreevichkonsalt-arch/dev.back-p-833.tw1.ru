# Progress

## Реализовано (2026-09-01)

### Выдача каталога / поиска (round-robin по моделям)
- [x] Группа = `model_id`; только `sort=default`; кастом вне витрины
- [x] `ProductRoundRobinSorter` + юнит-тесты
- [x] Каталог `CatalogProductListingService` / `getMenuProducts`
- [x] Поиск autocomplete + `/search/products` без потолка 20 на полную выдачу
- [x] OpenAPI 1.10.2

## Реализовано (2026-08-31)

### Избранное
- [x] Гостевая сессия + user favorites, sync при auth
- [x] OpenAPI 1.10.0, тесты FavoritesApiTest

## Реализовано (2026-08-05)

### API (16 эндпоинтов)
- [x] Auth, Profile, Leads, Pages, Catalog, Search — из БД

### Админ-панель (этапы 0–5)
- [x] Фундамент, заявки, заказы, медиатека
- [x] Каталог CRUD + seed
- [x] Галерея фото товара (multi-upload, предпросмотр, удаление)
- [x] Страницы — блоки + typed forms
- [x] Поиск — частые запросы, категории, рекомендации
- [x] Пользователи API — список, просмотр профиля

### Инфраструктура
- [x] `ContentFallbackTrait` — управление JSON-fallback
- [x] Seed: catalog, pages, search, **settings** (тестовые коллекции и габариты)
- [x] Настройки каталога: CRUD справочников, исправлено сохранение коллекций/габаритов + отображение ошибок
- [x] API тесты 13/13

## Осталось (опционально)
- [ ] OpenAPI, CDN, контент от редакции
