# Оптимизация: поиск и каталог

Документ для поэтапного обсуждения. Статус этапов: `идея` → `согласовано` → `в работе` → `готово` → `отложено`.

Контекст проблемы: время ответа поиска и листинга до ~8 с при ~1600 SKU (`scopeMode=all`). Основная архитектура — полная выборка scope в PHP, in-memory поиск, FileCache с «толстыми» документами.

### Ограничения и инварианты (не обсуждаем на этой оптимизации)

- **Аудитория смешанная:** и гости, и дилеры (Bearer) — один и тот же UI/каталог; замеры и приоритеты нужны для **обоих** путей, без фокуса только на одном.
- **Порядок товаров совпадает:** для одних и тех же параметров запроса (scope, `sort`, фильтры, page, `collection`, …) **последовательность slug/id в `items` у гостя и у дилера должна быть одинаковой**. Меняются только поля цен/скидок/акций в карточке, не порядок строк.
- **Логику сортировки и порядка выдачи не меняем:** round-robin по моделям, `ProductRoundRobinSorter`, `CatalogListingSort`, приоритеты `SearchCatalogPriorityService` / listing priority, порядок в поиске (`SearchProductOrderingService`, `SearchRanker`, `SearchTitleMatcher`) — **зафиксированы**. Оптимизация только ускоряет **тот же результат** (кэш, batch, SQL, индекс), а не новые правила ранжирования.
- **Проверка при внедрении:** regression-тест или snapshot «ordered slugs» guest vs dealer на фиксированном наборе URL (этап 0 + CI по желанию).

---

## Этап 0. Замеры и baseline (обязательно перед кодом)

**Цель:** понять, какой сценарий даёт 8 с, и не оптимизировать вслепую.

| # | Задача | Статус | Заметки |
|---|--------|--------|---------|
| 0.1 | Зафиксировать типичные URL: `/api/v1/search`, `/search/products`, `/catalog/menu`, `/catalog/products` | идея | |
| 0.2 | Матрица сценариев: **гость и дилер** (трафик смешанный); плюс `collection=true`, фильтры, page/sort | идея | Для каждой ячейки — p50/p95; **сверка порядка slug guest vs dealer** на тех же query |
| 0.3 | Замер: cold vs warm (после rebuild кэша `searchable-products`) | идея | |
| 0.4 | Backend: число SQL-запросов и wall time на один request (debug/log) | идея | |
| 0.5 | Размер blob в FileCache для `searchable-products` | идея | |
| 0.6 | Frontend: один запрос или цепочка (bootstrap + search + menu) | идея | |

**Критерий готовности этапа:** таблица «сценарий → p50/p95 → узкое место (гипотеза)».

### Baseline dev (2026-09-28, `scripts/bench-stage0-api.py`, 5 прогонов, curl wall time)

Хост: https://dev.back-p-833.tw1.ru · дилер: Bearer после `POST /api/v1/dealer/auth/login`.

| Сценарий | HTTP | p50 | max | bytes | Гипотеза узкого места |
|----------|------|-----|-----|-------|------------------------|
| `GET /api/v1/search/bootstrap` | 200 | **0.15 s** | 0.16 s | 26k | OK |
| `GET /api/v1/search?q=диван&limit=5` (гость) | 200 | **11.2 s** | 11.8 s | 12k | match + `getSearchableProducts` + vocabulary + rank; **не** menu |
| `GET /api/v1/search?q=диван&limit=5` (дилер) | 200 | **25.5 s** | 26.3 s | 12k | **N× `CatalogProduct::find()` на все matched SKU** до slice (исправлено в коде: batch + только top-N) |
| `GET /api/v1/search/products?q=диван&page=1` | — | — | — | — | прогон оборван по timeout сети; повторить |
| `GET /api/v1/catalog/menu` | 200 | **0.13 s** | 0.25 s | 27k | OK |
| `GET /api/v1/catalog/products?page=1&perPage=24&sort=default` (гость) | 200 | **0.27 s** | 0.28 s | 51k | OK на dev |
| `GET /api/v1/catalog/products?…` (дилер) | 200 | **0.41 s** | 0.43 s | 52k | OK |
| `GET /api/v1/catalog/products?…&collection=true` (гость) | 200 | **0.44 s** | 0.49 s | 99k | тяжелее payload |
| `GET /api/v1/catalog/products?…&collection=true` (дилер) | 200 | **0.95 s** | 1.0 s | 103k | pricing overlay |

**Инвариант порядка (разовая проверка):** `search/products` guest vs dealer — slug совпали (n=24).

**Статус этапа 0:** 0.1–0.2 частично · 0.3–0.6 в работе · скрипт: `DEALER_USER=… DEALER_PASS=… python3 scripts/bench-stage0-api.py`

**P1 в работе (после baseline):** 1.4 `getMatchBootstrap()` без recommended · 3.1 batch + dealer overlay только на top-N / page (см. `SearchService`).

### После деплоя d35f044 (2026-09-28, 3 прогона)

| Сценарий | p50 | Было (p50) |
|----------|-----|------------|
| search autocomplete (гость) | **9.5 s** | 11.2 s |
| search autocomplete (дилер) | **11.1 s** | **25.5 s** |
| search/products p1 (гость / дилер) | **~10 s / ~9.7 s** | timeout |
| catalog/products p1 | ~0.24 s | ~0.27 s |

Порядок slug guest vs dealer: **OK** (search/products, catalog/products).

---

## Этап 1. Поиск — данные и кэш

**Цель:** убрать тяжёлую десериализацию и не тащить полные карточки листинга в индекс поиска.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 1.1 | Разделить «индекс для match» и «карточка для ответа» | Match по лёгким полям (`title`, slug, taxonomy, `_searchText`); `toPublicProduct` / pricing — только для top-N | Меньше RAM и размер кэша | идея | |
| 1.2 | Кэш vocabulary отдельно от documents | `buildTokenVocabulary` не пересчитывать на каждый keystroke из полного массива | Быстрее `/search` при частых запросах | готово (код) | TTL / invalidation при импорте |
| 1.3 | Redis (или Memcached) вместо FileCache для больших ключей | `searchable-products`, при необходимости vocabulary | Меньше I/O на dev/prod | идея | Инфра на хостинге |
| 1.4 | Не вызывать полный `getBootstrap()` внутри `search()` | Только `frequent` + `categories`; recommended — отдельный endpoint / клиентский кэш | Меньше SQL и CPU на каждый search | идея | Контракт API с фронтом |
| 1.5 | Warm job после импорта каталога | Прогрев `searchable-products` + vocabulary, не ждать первого пользователя | Нет «8 с после деплоя» | готово (код) | `php yii catalog-cache/warm` |

**Обсуждение этапа 1:**

- 

---

## Этап 2. Поиск — алгоритм match и ordering

**Цель:** не делать 3–5 полных проходов по ~1600 SKU и Levenshtein по всему каталогу на каждый запрос.

**Инвариант:** порядок и состав выдачи поиска — как сейчас; ускорение без смены правил match/order (см. ограничения выше).

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 2.1 | Ранний выход после exact / cascade | Не вызывать `SearchRanker` и `stageSimilar`, если уже есть достаточный результат | Быстрее типичные запросы | идея | Сохранить текущее UX-поведение |
| 2.2 | Ограничить fuzzy (Levenshtein) | Prefilter по \|len(title)−len(query)\| ≤ maxDistance до Levenshtein | Меньше CPU на similar | готово (код) | Семантика match не меняется |
| 2.3 | Inverted index / token → product ids | Предфильтр по токенам запроса, match только по подмножеству | O(совпадения) вместо O(каталог) | идея | Сложность vs FTS |
| 2.4 | PostgreSQL FTS (или trigram) | `tsvector` + GIN по title/search_text; rank в SQL | Масштабирование без ES | идея | Миграция, синхронизация при импорте |
| 2.5 | Внешний search (Meilisearch / Elasticsearch) | Async index при импорте; API только query + hydrate N карточек | Лучший UX search при росте SKU | идея | Операционка |
| 2.6 | `orderAll` только для нужного limit | Для подсказки (limit 5–20) не сортировать весь matched set round-robin | Меньше CPU | идея | Только если **бит-в-бит** совпадает с текущим `orderAll`; иначе отложено |

**Обсуждение этапа 2:**

- 

---

## Этап 3. Поиск — дилер и цены

**Цель:** убрать N× `CatalogProduct::find()` на matched SKU.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 3.1 | Batch load по slug/id для top-N | Один `where(['slug' => $slugs])->with(...)` + map | Меньше round-trips к БД | идея | |
| 3.2 | Pricing overlay из уже загруженных promotion maps | Переиспользовать `CatalogPromotionResolver` (in-memory после одного load) | Как в листинге | идея | |
| 3.3 | Опционально: lite-поля в search для дилера без полного `toListingCard` | Только price fields + badge | Меньше eager load | идея | Контракт с фронтом |

**Обсуждение этапа 3:**

- 

---

## Этап 4. Каталог — листинг и round-robin

**Цель:** не загружать и не сортировать весь scope (~1600 строк) ради 24 карточек на странице.

**Инвариант:** порядок id/slug на странице — результат текущего PHP round-robin (и priority prepend); guest и dealer — **один и тот же порядок**, различия только в ценах на карточках. Любой materialized/SQL-кэш — только **реплика** существующего алгоритма, не замена правил.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 4.1 | Предвычисленный порядок listing (materialized) | Snapshot ordered ids per scope+sort, считать **тем же кодом**, что сейчас | Pagination через slice id[] | идея | Пересчёт при импорте; golden test guest=dealer order |
| 4.2 | Round-robin только в SQL (window / recursive) — spike | POC: воспроизвести **идентичный** порядок без PHP sort | Зависит от сложности правил | идея | При расхождении — не внедрять |
| 4.3 | Кэш отсортированных id per scope+sort | Один список id для guest и dealer; dealer только пересчитывает price fields | Warm path для default sort | готово (код) | Invalidation; порядок id общий |
| 4.4 | `collection=true`: не держать весь scope в RAM | Пагинация по collection_id в SQL, SKU per group — отдельным запросом | Меньше памяти на больших direction | идея | |
| 4.5 | HEAD / count без full fetch | Уже частично есть; проверить, что count не тянет round-robin rows | Быстрее prefetch total | идея | |

**Обсуждение этапа 4:**

- 

---

## Этап 5. Каталог — фильтры (facets)

**Цель:** не дублировать полную выборку scope для `filters` + `items`.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 5.1 | Facets одним запросом / subquery по scope | Без `select('p.id')->column()` всех id в PHP | Меньше памяти и SQL | идея | |
| 5.2 | Кэш facets для scope без активных фильтров | Как products cache, отдельный ключ | Warm listing pages | готово (код) | |
| 5.3 | Дилер: price facet без correlated subquery на каждую строку | JOIN promotions или precomputed effective price | Быстрее фильтр priceMin/Max | идея | `CatalogPromotionDealerListingPrice` |

**Обсуждение этапа 5:**

- 

---

## Этап 6. Каталог — hydration карточек

**Цель:** уменьшить объём eager load на 24 SKU.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 6.1 | Audit `productRelations()` для listing | Убрать modelInteriorImages / dimensionImages / full fabricCollections если не в card | Меньше JOIN | готово (код) | video/layout/colorImages убраны из listing eager load |
| 6.2 | Swatches: только preview limit без полного linked colors | Уже `previewLimit=3`; проверить lazy load | Меньше N+1 | идея | |
| 6.3 | Отдельный DTO «listing card lite» vs detail | Меньше полей в JSON | TTFB + parse на клиенте | идея | Breaking change? |

**Обсуждение этапа 6:**

- 

---

## Этап 7. Кэш листинга и API-форма ответа

**Цель:** расширить cache hit rate и уменьшить монолитный `/catalog/menu`.

| # | Направление | Суть | Ожидаемый эффект | Статус | Решения / риски |
|---|-------------|------|------------------|--------|-----------------|
| 7.1 | Кэш listing для дилера по tier скидки (не personal) | Ключ `products:dealer:tier:X:scope` для p1 default без фильтров | Warm path для B2B | готово (код) | Персональные промо вне tier — cache miss по ключу; порядок slug общий |
| 7.2 | Расширить кэш: page 1 + другие perPage / subcategory scope | Осознанно, с лимитом ключей | | идея | |
| 7.3 | Разделение API: menu vs listing (или `?include=menu`) | Клиент кэширует menu; listing — отдельно | Меньше payload | идея | Фронт |
| 7.4 | ETag / Cache-Control для menu bootstrap | CDN/browser cache | | идея | |

**Обсуждение этапа 7:**

- 

---

## Этап 8. Инфра и эксплуатация

| # | Направление | Суть | Статус | Заметки |
|---|-------------|------|--------|---------|
| 8.1 | OPcache, PHP-FPM workers, DB connection pool | Baseline prod | идея | |
| 8.2 | Индексы БД под join листинга | `(is_active, is_custom, subcategory_id)`, fabric joins | идея | Проверить EXPLAIN |
| 8.2b | Индексы под SQL поиска (без кэша) | `m260928_190000_search_catalog_db_indexes`: products searchable sort, search bootstrap, subcategory icon | **готово** | EXPLAIN до/после на prod |
| 8.3 | Мониторинг p95 по route | `/search`, `/catalog/menu` | идея | |

**Обсуждение этапа 8:**

- 

---

## Приоритизация (черновик для обсуждения)

| Приоритет | Этап | Почему |
|-----------|------|--------|
| P0 | 0 | Без baseline не выбираем этапы |
| P1 | 1.4, 1.1, 3.1 | Дешёвые изменения, частый search |
| P1 | 4.1 или 4.3 | Главный bottleneck каталога (full scope sort) |
| P2 | 2.1–2.3, 5.1, 6.1 | Средняя сложность, большой эффект |
| P3 | 2.4–2.5, 7.3 | Стратегически, больше работы |

**Согласованный порядок внедрения:**

1. 
2. 
3. 

---

## Журнал обсуждений

| Дата | Участники | Решения |
|------|-----------|---------|
| 2026-09-28 | | Документ создан из code review производительности |
| 2026-09-28 | | Трафик смешанный (гость + дилер). Инвариант: одинаковый порядок товаров guest/dealer; **логику сортировки и порядка выдачи не меняем** — только ускорение |
| 2026-10-03 | | 2.2 prefilter Levenshtein · 7.1 кэш p1 для дилера · главная journal/products cache · listing `src` снова **medium** (mini давал мыло на UI) · `catalog-cache/warm` |

---

## Связанный код (ориентиры)

- Поиск: `services/search/SearchService.php`, `services/catalog/CatalogService::getSearchableProducts()`
- Листинг: `services/catalog/CatalogProductListingService.php` (`fetchRoundRobinSourceRows`, `buildFiltersPayload`)
- Кэш: `services/cache/ApiResponseCache.php`, `config/web.php` (FileCache)
- Unified API: `services/catalog/CatalogUnifiedService.php`
- Порядок (не менять семантику): `ProductRoundRobinSorter`, `CatalogListingSort`, `SearchProductOrderingService`, `SearchCatalogPriorityService`
