# Active Context

## Импорт моделей — полигоны и файл 3D (2026-09-17) ✅ локально

**Запрос:** колонки AB «Полигоны для 3д», AC «Ссылка на файл 3д» в импорте; UI между Slug и бейджем; отдельный API загрузки 3D-файла; не отдавать в GET товара.

**Реализовано:**
- Миграция `m260917_190000`: `catalog_models.polygons_3d`, `file_3d_url`, `file_3d_id` + папка медиа `models-3d`
- Импорт: заголовки → DTO → сохранение URL/полигонов (скачивание файла при импорте **не** делается)
- Админка формы модели: поля после Slug, до subtitle/бейджа
- `POST /api/v1/catalog/models/{slug}/3d-file` — скачать по URL (тело/`file_3d_url`) или multipart `file`; ответ `{ modelSlug, sourceUrl, fileUrl, mediaId, filename }`
- `toCatalogApiPayload` / карточка товара — без polygons/file3d
- OpenAPI **1.10.20**; шаблон Excel: AB/AC вставлены перед тех.фото
- Тест reader: `testReadsPolygonsAndFile3dColumns` ✅

## План: оформление заказа — дилер → менеджер, гость → ЮKassa (2026-09-17)

**Запрос:** при checkout из корзины два сценария:
1. **Дилер** — заявка менеджеру (не онлайн-оплата).
2. **Гость (незарег.)** — редирект на оплату ЮKassa.

**Сделано (2026-09-19):**
- ✅ Ключи в локальном `.env` (не в git): `YOOKASSA_SHOP_ID/SECRET_KEY/RETURN_URL`
- ✅ Guest `POST /orders` → `status=pending_payment`, `paymentMethod=online`, ответ `paymentConfirmationUrl`
- ✅ Webhook `POST /api/v1/payments/yookassa/webhook` (IP allowlist + verify via GET payment API)
- ✅ Cron `php yii order/cancel-unpaid` (24ч, `orderUnpaidCancelHours`)
- ✅ HTTP-клиент без SDK (`YooKassaClient` / `YooKassaPaymentService`)
- ✅ OpenAPI **1.10.22**
- ✅ Dealer email менеджеру (ранее)
- ✅ Commit `42b7d1d` push master + deploy **full** на https://dev.back-p-833.tw1.ru
- ✅ Server `.env` YOOKASSA_*; cron `15 4 * * * order/cancel-unpaid`

**Webhook URL для ЛК ЮKassa:**  
`https://dev.back-p-833.tw1.ru/api/v1/payments/yookassa/webhook`  
События: `payment.succeeded`, `payment.canceled`

---

## План доработок кэшбека CASH-01…05 (2026-09-16) — согласовано

**Решения продукта (2026-09-16):**
1. Весь ассортимент; в заказе **либо промокод, либо кэшбек** (не оба).
2. Кэшбек **вычитается из итоговой суммы** к оплате (`total = subtotal − promo − cashback`).
3. Блокировка кэшбека при **любом** промокоде (текущая логика верна).
4. База накопления = **`subtotal` после персональной скидки дилера** (`orders.subtotal_amount` — уже так).
5. **Отменённый заказ** — вычитать из `period_total` текущего месяца (monthly accrue уже исключает cancelled; **live `period_total` — нет**).
6. Уведомление о сгорании — **email** на `dealer_profile.email` (за 7 дней).

**Статус по пунктам:**

| ID | Требование | Статус | Доработка |
|----|------------|--------|-----------|
| CASH-01 | Виджет «Мой кэшбек» | ✅ API | Фронт ЛКД по `GET /dealer/bonuses`. |
| CASH-02 | TTL 30 д + email −7 д | ✅ | `CashbackExpiryMailer`, `cashback/notify-expiring`, ledger `TYPE_NOTIFY`. |
| CASH-03 | Корзина, частичное | ⚠️ | **P0 ✅** cap balance; фронт — чекбокс (P4). |
| CASH-04 | Месяц, начисление 1-го | ✅ | Cron в `params.cashbackCron`; база = subtotal после дилерской скидки. |
| CASH-05 | Промо XOR кэшбек | ✅ | Весь ассортимент. |
| Extra | Профиль + корзина | ✅ | `GET /dealer/profile` → `cashback`; корзина ✅. |
| Cancel | Вычет + refund | ✅ | `OrderController::actionUpdate` → `handleOrderCancelled`; refund если accrual ещё активен. |

**Фазы:** P0–P3, P5 ✅; **P4** — фронт ЛКД/корзина (отдельный репозиторий).

## Deploy dev (2026-09-16)

Коммит `1ea2951` → push master → deploy **full** на https://dev.back-p-833.tw1.ru  
Миграция `m260916_120000_media_image_variant_large` ✅; OpenAPI regenerate ✅; `media/regenerate-variants` 333/335 OK (2 без оригинала).

## Аудит кэшбека при оформлении заказа (2026-09-16)

**Цепочка:** `PATCH /cart/cashback` → `dealer_cart_checkout.cashback_amount` → `POST /orders` → `CartCheckoutService::getCheckoutTotalsForLines` → `Order.cashback_used_amount` → `CashbackService::spend` + ledger → `resetCheckout`.

**Работает:** взаимоисключение промо/кэшбек; распределение по позициям (`OrderLinePricingAllocator`); списание баланса + ledger; сброс checkout; `discounts.cashback` в ответе заказа; порядок скидок dealer→promo→cashback; unit-тесты allocator/tier OK.

**P0 fix (2026-09-16):** `resolveCashbackUsedAmount` / `resolveCheckoutCashbackUsed` — cap `min(requested, balance, subtotal − promo)` в `getCheckoutTotals*` и `enrichPayload`. Тест `CartCheckoutServiceTest::testCheckoutTotalsCapCashbackByBalance`.

## Качество изображений баннеров/CMS (2026-09-16) ✅ локально

**Реализовано:**
- Вариант **large** (`*_l.webp`, до 1920px, WebP q92); medium q88; миграция `m260916_120000`.
- `MediaUrlResolver::forPageContent()` — `src` = large → original (не medium). Старые `*_m.webp` URL резолвятся в полный payload.
- Picker контент-страниц: **MODE_ID** (media ID); URL из БД → ID при открытии формы.
- PageContentService, JournalArticleService, VacancyService — page resolver.
- Каталог: `src` = medium без изменений.
- `php yii media/regenerate-variants` — пересборка large для существующих файлов.

**Пример:** home hero `_m.webp` 34 KB → API `src` = `_l.webp` **75 KB** (+ original PNG в srcSet).

**Deploy:** migrate + regenerate-variants + сброс API-кэша. Seed `/images/…` вне медиатеки — без изменений.

## Главная — вкладка «Коллекции» (2026-09-16)

**Запрос:** в `admin/content-page/blocks?id=5&tab=collections` поле «Коллекция» → «Направление».

**Реализовано локально:**
- Dropdown: `catalog_direction_id` из `catalog_directions` (было `catalog_collection_id` / `catalog_collections`).
- Ссылка в подсказке: Настройки → Направления.
- `BlockFormBuilders::collectionsFromPost/ToForm` — slug направления в API `collections[].id`; fallback: старый slug линейки → direction_id.
- `titleUppercase` сохраняется через hidden-поле при редактировании.
- href слайдов: `/catalog/{direction-slug}/…`.

## Сессия 2026-09-14

**GET /api/v1/orders — preview images (2026-09-14, deploy dev ✅):**
- `OrderSummary`: вместо `previewImages[]` (CatalogImage) — `images[]` (mini-URL строки, до 3) + `extraCount` (число позиций сверх 3, 0 если ≤3).
- `OrderItemApiEnricher::buildSummaryImages()` — mini из `srcSet.mini`, fallback `src`.
- OpenAPI **1.10.19**; commit `1d396ac` push + full deploy ✅.

## POST /api/v1/orders — 500 (2026-09-05) ✅

**Причина:** в `OrderController` не было `use app\services\order\OrderAttachmentUploadService` — PHP искал класс в `app\controllers\api\v1\`, конструктор падал до обработки запроса.

**Исправление:** добавлен import в `controllers/api/v1/OrderController.php`. **Deploy dev (2026-09-05):** `files` ✅.

**Сверка ЛКД заказы (2026-09-05):** код ↔ OpenAPI 1.10.11 на dev — детали заказа отдают comment/attachment/цены/promo/payment/documents на уровне позиций; список — только `OrderSummary`.

**Аудит цен дилера — корзина/заказ (2026-09-11, deploy dev ✅):**
- Две цены: `retailPrice` (розница) + `dealerPrice` (персональная скидка); в корзине `priceDisplay` вместо `price`.
- Порядок скидок: дилерская → промокод от `subtotal` → кэшбек.
- Корзина/заказ API: `retailSubtotal`, `dealerDiscountAmount`, `discounts` `{ dealer, promo, cashback }`.
- Позиция заказа: snapshot `retailPrice`, `retailLineTotal`, `dealerPrice`, `dealerDiscountAmount`, `paidLineTotal`.
- Fix: `totalAmount` включает `cashlessSurchargeAmount` при безнале.
- OpenAPI **1.10.17**; `OrderLinePricingHelper`; тесты `OrderApiServiceTest` (+dealer/promo), `OrderLinePricingHelperTest`.
- **Каталог/карточка (2026-09-11):** `buildPricingApiPayload()` — единый расчёт retailPrice/dealerPrice; `toMenuApiItem($dealer)` и листинг; fabrics[] с dealerPrice; search `price` = розница (не скидка).

**Вакансии админка (2026-09-11, deploy dev ✅):**
- Вкладка «Вступление» (values): галерея `slides[]` в сетке как ракурсы модели.
- Таблица `vacancy_directions`; вакансии через `direction_id` (CRUD направлений на вкладке «Направления»).
- Вкладка «Вакансии» (jobs) в редакторе страницы: список по направлениям + «Добавить вакансию».
- Форма вакансии: 3 строки; slug через `AdminHtml::slugField`; кнопки `admin-btn`; заголовок только в topbar. Вкладка jobs без дублирующего h3 и кнопок «Новая/Все вакансии».
- API: `groups[]` из vacancy_directions; values.slides[]; detail — `directionId`, `directionTitle`. OpenAPI **1.10.18**. Fix: группировка jobs по `direction_id`; инвалидация API-кэша при save направлений. **is_active у вакансий не используется:** есть в БД = показывается в API. **Deploy dev (2026-09-12):** commit `956c7bf` push + full ✅ — jobs в API (production: 5, upravlenie: 1).

## DaData — подсказки адреса (2026-09-05) ✅

**Реализовано:** как в wingsBack — `DaDataClient`, `DaDataSuggestionFormatter`, `POST /api/v1/dadata/suggest/city` и `/address` (без auth). Маршрут → `api/v1/da-data/...` (Yii ID для `DaDataController`). Ключи в `.env`. OpenAPI **1.10.11**. **Deploy dev (2026-09-05):** full + fix routes, ключи в server `.env` ✅.

**Кастом-SKU slug:** «Прямой диван Адриано кастом» → `pryamoy-divan-adriano-kastom`. Карточка по slug доступна; в витрине/поиске кастом скрыт (`is_custom`).

## SKU title/slug — подкатегория (2026-09-05) ✅

**Исправлено:** `ProductTitleBuilder::resolveTypeLabel()` — label подкатегории (не «Диван»). Миграция `m260905_200000`. **Deploy dev:** app ✅.

## model.customProductSlug (2026-09-05) ✅

`CatalogModel::toCatalogApiPayload()` → `customProductSlug`. OpenAPI **1.10.12**. **Deploy dev:** full ✅.

## Дилер — ИНН необязателен при создании (2026-09-05) ✅

Админка «Новый дилер»: ИНН опционален; логин `d{inn}` или `d{random}`; миграция `m260905_210000` (inn nullable). **Deploy dev (2026-09-05):** app ✅. В ЛК при первом входе ИНН по-прежнему обязателен.

## SKU title/slug — коллекция ткани (2026-09-03) ✅

**Реализовано:** `ProductTitleBuilder::build()` — `{подкатегория} {коллекция модели} {цвет} {коллекция ткани} {design_code}`. Миграция `m260903_150000` применена локально (backfill через `syncForModel`). Превью в админке обновлено. Тесты: `ProductTitleBuilderTest`, `CatalogModelProductSyncServiceTest`.

## Выдача каталога round-robin (2026-09-01, актуализация 2026-09-30)

**Плоский листинг** (`layout=flat`, `GET /catalog/products` без `collection=true`):

- **`sort=default`** и **`popular`**: приоритетные SKU из `/admin/search?tab=catalog` (таблица `search_catalog_priority_models`, образец на модель, `sort_order`) — **только page 1**, только если в scope есть **направление** (`direction` или цепочка с линейкой); далее round-robin по **`model_id`**. Внутри модели для **`default`**: цена ↑, затем ткань→цвет; для **`popular`**: критерий популярности. **`collectionKey`** = линейка мебели — не ставить подряд две SKU одной линейки, если в круге есть альтернатива. **Ротация** bucket по позиции модели (смешение цветов между моделями).
- **`sort=price_*` / `new`**: тот же RR по моделям; для `price_*` модели в круге дополнительно по минимальной цене SKU (`orderGroupsByBucketHead`).
- **`sort=alphabet`**: отдельно — RR по **линейке мебели** A→Z × цена ↑ (без ротации), без prepend приоритетов.

**Поиск** (`SearchProductOrderingService`): приоритеты из той же таблицы + RR по линейкам A→Z × цена (не путать с каталогом `default`).

**`collection=true`**: пагинация по линейкам; приоритетные SKU **не** prepend; внутри линейки — `sortListingProductIds`.

**Сдвиг цвета:** `ProductRoundRobinSorter` — группа на позиции `p` начинает с `p`-го варианта (rotate bucket). Пагинация: полный порядок строится один раз, `array_slice(offset, limit)`; page 2+ учитывает слоты приоритетов на page 1.

**Кэш:** page1 `default` без фильтров отключён, если у направления в scope есть приоритеты.

**Deploy dev (2026-09-03):** `./deploy/deploy.sh app` ✅ — миграция `m260903_150000` на dev, SKU title/slug с тканью.

Единый `GET /catalog/menu/{slug}`: `items` — SKU раздела; `modelLines` / `menuCollections` — все линейки. **`GET /catalog/products`** без scope — **все** SKU (round-robin); scope/slug — фильтр. **`GET /catalog/menu`** без scope — меню, `items: []`.

## fabricColor.label (2026-09-01)

Формула: `{название коллекции} {design_code}` через пробел, пример `GUCCI 422`. Раньше вместо design-code брался label из справочника цветов (`Gucci Белый`). Миграция `m260901_120000` пересчитала `api_label` у существующих цветов. OpenAPI **1.10.1**.

**`fabricColor.colorName` (2026-09-01):** русское название из `catalog_colors.label` (напр. «Белый», «Бежевый»); `null`, если цвет справочника не привязан. OpenAPI **1.10.4**.

## Избранное vs wingsBack (сверка 2026-09-01)

Модель совпадает: гость **или** user, оба заголовка сразу допустимы; при Bearer запись идёт на `user_id`. Перенос после регистрации/входа: `guestSync` на verify-code / yandex / dealer login, если есть `X-Session-ID` или `sessionId`; иначе ручной `POST /favorites/sync`.

Отличия контракта (не копировать wings 1:1): путь `/api/v1/…`, JSON camelCase, `productId` = slug SKU (не numeric `product_id`). `session_id` в теле/query принимается как алиас.

## Избранное (реализовано 2026-08-31)

Подтверждено 2026-09-01: гостевое добавление через сессию готово (`POST /favorites/add` + `X-Session-ID`). Аннотации Swagger есть (тег «Избранное», header `X-Session-ID`, схемы). Живой `/swagger/json-schema` берёт `runtime/openapi.json` после `php yii open-api/generate`.

По образцу wingsBack: гостевая сессия `X-Session-ID` / `sessionId`, merge при логине (`guestSync`), ручной `POST /api/v1/favorites/sync`. `productId` = slug SKU, JSON camelCase. OpenAPI **1.10.0**. Миграция `m260831_180000_favorites`. Тесты `FavoritesApiTest` 5/5 + AuthApiTest 3/3.

**Эндпоинты:**
| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/favorites/add` | Bearer **или** X-Session-ID |
| POST | `/api/v1/favorites/remove` | Bearer **или** X-Session-ID |
| POST | `/api/v1/favorites/check` | Bearer **или** X-Session-ID |
| GET | `/api/v1/favorites/list` | Bearer **или** X-Session-ID |
| POST | `/api/v1/favorites/sync` | Bearer + sessionId |

Авто-merge: `POST /auth/verify-code`, `POST /auth/yandex`, `POST /dealer/auth/login` → `guestSync`.

## Catalog v3 — иерархия

**Цепочка:** Направление → Коллекция → Категория → Подкатегория → Модель → Фактура (+ глобальные цвета)

| PR | Статус | Содержание |
|----|--------|------------|
| 3a | ✅ | Динамические ценовые категории (`catalog_price_categories`) |
| 3b | ✅ | Глобальный справочник цветов (`catalog_colors`) |
| 3c | ✅ | Иерархия: `catalog_directions`, `catalog_categories`, каскад в форме модели, удалён `CatalogType` |
| 3d | ✅ | API catalog v3: navigation, products (filters/sort/facets), OpenAPI 1.8.0 |
| 4 | — | Bitrix sync |

## PR-3c (завершён)

- Миграция `m260812_240000_catalog_hierarchy` (идемпотентная)
- `catalog_groups` → `catalog_directions`, `group_id` → `direction_id`
- Новая таблица `catalog_categories` (дефолт «Каталог» на коллекцию)
- Подкатегории привязаны к `category_id`; `type_id` удалён с моделей/товаров
- Админка: Направления, Категории; коллекции → `direction_id`
- Форма модели: каскад Направление → Коллекция → Категория → Подкатегория (`admin-catalog-cascade.js`)
- `CatalogService::getMenu()` — `directions` + legacy `groups`
- Seed обновлён под новую иерархию

## Навигация админки (Настройки)

Направления, Коллекции, Категории, Цвета, Бейджи. Ценовые категории — в форме модели.

## Зарплата (расчёт, авг 2026)

Пересчёт смен июнь–июль: ставка 320 ₽/ч, −1 ч неоплачиваемый перерыв/смена, 11–13.07 @400. Июнь 19 200 ₽ (60 ч), июль 75 440 ₽ (229 ч). Выплаты июля (только июль): 57 940,40 ₽. Недоплата июля: 17 499,60 ₽; июнь отдельно: 19 200 ₽.

## Команды

```bash
php yii migrate/up
php yii seed/catalog-v2
php yii seed/catalog
php tests/bin/yii migrate/up   # тестовая DB
vendor/bin/codecept run unit services/CatalogModelProductSyncServiceTest
php yii serve 0.0.0.0:8080

# Деплой dev (режимы: files ~2 мин | app ~5 мин | full ~2–3 мин)
./deploy/deploy.sh files
```

Скилл агента: `.cursor/skills/back-dev-deploy/SKILL.md`

## Последний коммит

**Swagger dev (2026-09-02):** OpenAPI **1.10.6** на https://dev.back-p-833.tw1.ru/swagger/json-schema — `/catalog/products` HEAD+GET, пагинация page/perPage как menu.

**`c04cdf3`** — сдвиг цветов в round-robin + `fabricColor.colorName`. OpenAPI 1.10.4→1.10.5 (products listing).

**`da2a814`** — round-robin в `recommended` на карточке товара. Задеплоен на https://dev.back-p-833.tw1.ru (2026-09-01), OpenAPI 1.10.3.

**`1b5de95`** — гостевое избранное (`X-Session-ID`) и round-robin выдачи каталога. Задеплоен на https://dev.back-p-833.tw1.ru (2026-09-01): миграции `m260831_180000_favorites`, `m260901_120000_fabric_color_api_label_design_code`, OpenAPI схема пересобрана.

**`6403eca`** — fabrics.price, fabricColor label, path /catalog/menu/{slugs}, merge категорий. Задеплоен на prod (2026-08-31), merge-duplicates выполнен.

**Prod-баг импорта (2026-08-31):** под PHP-FPM `PHP_BINARY` пустой → `exec(' yii fabric-import/run …')` → `sh: 1: : Permission denied` в `runtime/fabric-import/run-*.log`, статус `queued` навсегда. Fix: `ImportRunWorkerLauncher::phpBinary()` ищет `/usr/bin/php8.3` и т.д. Застрявшие run #7/#8 (167 строк) доведены до `completed` вручную на сервере.

API каталога/поиска выровнен под фронт:
- `GET /api/v1/catalog/menu` — unified menu + taxonomy `categories[]`, `modelLines`, листинг `CatalogProductCard`
- `collection` в query = direction (`a-plus`); `modelLine` = линейка (`artemida`)
- Frontend slugs: `url_slug` на categories/subcategories (миграция `m260829_150000`)
- `GET /api/v1/catalog/products/{slug}` — карточка товара
- Search: `/product/{slug}`, `icon` в categoriesFound, `GET /api/v1/search/products`
- **HEAD `/api/v1/catalog/menu`** — `X-Total-Count` (+ X-Page, X-Per-Page, X-Sort, X-Scope-Mode), OpenAPI 1.9.1
- Deprecated aliases: `/menu/products`, `/navigation`, `/products`, `/filters`

**GET/HEAD `/api/v1/catalog/menu/{slugs}` (2026-08-31):** path-алиас единого каталога. Сегменты автоопределяются как направление, коллекция (линейка), категория или подкатегория; несколько slug комбинируются (`/menu/artemida/pryamoy-divan`, `/menu/a-plus/divany/pryamye`). `/menu/products` по-прежнему алиас query-API. Неизвестный slug → 404 «Раздел каталога не найден». OpenAPI 1.9.4. Дубли «Прямой диван»: `pryamoy-divan` (импорт) и `straight`/`pryamye`.

## Импорт моделей — габариты и «Спальное место» (2026-08-29, локально)

- Миграция `m260829_180000`: `has_sleeping_place`, `corner_depth_mm` на `catalog_models` / `catalog_products`
- Импорт: колонка «Функция (для фильтра)» → `has_sleeping_place`; размер `2500×860х1200` сохраняется как строка + парсится в Ш/В/Г (+ 4-е значение → `corner_depth_mm`)
- Форма модели: габариты — Ш/В/Г/«Гл. угла» в одну строку, посадка/опоры — вторая строка; выравнивание label/input через CSS grid
- Флаг **`is_popular`** («Популярный товар») на модели → sync в SKU; `sort=popular` сортирует по нему
- Fix подкатегории в форме модели: `category_id` синхронизируется с подкатегорией; миграция `m260829_200000`; импорт ищет категорию по label
- **Импорт моделей:** опция «Пропускать создание, если нет ткани» (только модели)
- API: `overallSize` строкой; `cornerDepth` отдельно; фильтр ширины `widthMin`/`widthMax` + facet `filters.dimensions.width`; query `sleepingPlace=1`; facet `filters.functions[]` (`sleeping`)
- **Скрипт слияния дубликатов категорий:** `php yii catalog-category/merge-duplicates` (`--dryRun`) — sofa→divan, armchair→kreslo, подкатегории по slug/url_slug/label

Предыдущий: `02a1dff` — API каталога v3 (OpenAPI 1.8.0).

- `php yii serve 0.0.0.0:8080` — порт 8080; при зависшем процессе перезапуск (`kill` + serve). **Не** `php -S … web/index.php` — без стилей.
- **Локально (2026-09-10):** сервер `php yii serve 0.0.0.0:8080` запущен, document root `web/`
- **Админка:** http://127.0.0.1:8080/admin → логин http://127.0.0.1:8080/admin/site/login (`admin` / `admin123` после seed)
- **Локальная БД (2026-08-19):** удалены все модели каталога (46 шт.) + связанные цены/товары; осталось 4 legacy-товара без `model_id` — готово к тесту нового формата импорта
- **Флаг `custom` (2026-08-19):** `catalog_products.is_custom` (default false; true для SKU «кастом»); в админке не показывается; в API: `custom` в `CatalogMenuProduct`, `CartLineItem`, `OrderLineItem`; корзина `/api/v1/cart`, заказы `/api/v1/orders`; OpenAPI **1.4.0**
- **Аудит мусора (2026-08-19):** cleanup выполнен — удалены legacy-контроллеры/views, `FabricRegistryReferenceReader`, `HelloController`; миграция `m260819_170000` (unused fabric columns); исправлен seed (`CatalogProduct` import, `design_code`); тесты **89/89 OK**
- **Импорт тканей / swatch:** `catalog_colors.hex_color` заполняется из `CatalogFabricBaseColorPalette` при импорте и миграции `m260819_190000`; кружки на `/admin/fabric-collection` берут hex из справочника цветов
- `/swagger/json-schema` — отдаёт `runtime/openapi.json` (генерация: `php yii open-api/generate`, ~1 с; раньше 30–50 мин из‑за Symfony TypeInfo в default pipeline swagger-php). Пропуск если `isRuntimeFresh()`. HTTP не генерирует схему на лету.
- API `image.src`: `MediaUrlResolver` преобразует media ID → `/uploads/...` в PageContentService и JournalArticleService

## SuperCode

Конфиг режимов Memory Bank: `.supercode/modes/memory-bank/`.

## Выдача товаров (обзор 2026-08-29)

Клиентских сортировок и фильтров у legacy `GET /api/v1/catalog/menu/products` **нет**. Новый API v3 (2026-08-29):

- `GET /api/v1/catalog/menu` — **единый** эндпoинт: menu + navigation + (при scope) items/filters/meta
- `/menu/products`, `/navigation`, `/products` — алиасы того же handler (deprecated)

## Главная — блок «Коллекции»

Философия бренда (из баннера) + две карточки, в каждой — 3 фото с заголовком.

## API / Swagger

OpenAPI 1.2.0: catalog v3 (`directions`, `directionId`), главная (`philosophy`, slides в collections).

**Аудит 2026-08-19:** `docs/OpenApiSpec.php` восстановлен, версия **1.3.0**. Обновлены `CatalogImage` (+ `srcSet`, `width`/`height`), `CatalogImageSrcSet`, `CatalogFabricColor` (category nullable, без лимита 8), `CatalogModelPrices` (1–25), `SearchBootstrapResponse.recommended`, `SearchIndexResponse`.

## Редактор страниц (вкладки)

Все основные страницы контента в админке — единый вкладочный редактор (`_page_editor_tabs.php`), сохранение по вкладке:

| Страница | URL | Вкладки |
|----------|-----|---------|
| Главная | `blocks?id=&tab=` | hero, collections, products, partners |
| FAQ | | hero, intro, categories |
| Франшиза | | hero, intro, formats, audience, salonFormats, presentation |
| Дизайнерам | | hero, intro, materials, gallery |
| Контакты | | hero, info, contact, regions |
| Журнал | | hero, categories |
| Производство | | hero (+ seo), intro, comfort, stack (title+text+items) |
| О нас | `blocks?id=&tab=` | hero, intro, community (галерея), timeline (история + jobsIntro) |
| Вакансии | `blocks?id=&tab=` | hero, values (вступление), gallery, groups (вакансии) |

FAQ: в ответах ссылки в `paragraphs`; Swagger — `PageFaqResponse`, `PageFaqTextPart`, `PageFaqItem`.

## План поиска (2026-08-24)

Autocomplete как divan.ru: товары (`limit`, default 5, max 20), «Часто ищут» (подкатегория → коллекция), «Найдено в категориях», история. База: `SearchService`, `GET /api/v1/search`, min 3 символа.

**Утверждённые решения:**
| # | Тема | Решение |
|---|------|---------|
| 1 | История гостей | **localStorage** на фронте (без серверной истории для неавторизованных) |
| 2 | Max limit товаров | **20** достаточно |
| 3 | Что сохранять в истории | **Точная строка из поля поиска** (as typed), не подсказка/категория |
| 4 | Redis vs MySQL | **Не блокер** — история на клиенте; серверная история для auth — позже при необходимости (MySQL) |
| 5 | Когда писать историю | **Только submit или клик** по подсказке/товару/категории; не при каждом autocomplete |

**История (localStorage, фронт):**
- Ключ например `searchHistory` — массив строк, max 20, dedup по normalized (trim + lower), новые сверху
- Мин. длина для записи: 3 символа
- `GET /api/v1/search` **не** возвращает history — фронт читает localStorage для блока «История»
- Опционально позже: sync в API для авторизованных (`POST /search/history`) — не в MVP

**Этапы:** (1) limit + контракт API ✅, (2) поля модели в индексе ✅, (3) oftenSearched + categoriesFound ✅, (4) фронт localStorage history ✅ (`web/js/search-history.js`).

**Реализовано (2026-08-24):**
- `GET /api/v1/search?q=&limit=` — default 5, max 20; ответ: `query`, `correction`, `products`, `oftenSearched`, `categoriesFound` (без `history`)
- `SearchDocumentBuilder`, `SearchCatalogVocabulary`, `SearchOftenSearchedMatcher`, `SearchCategoriesFoundAggregator`
- OpenAPI **1.6.0**
- `web/js/search-history.js` — localStorage, max 20, dedup, min 3 символа, запись только при submit/клике (вызывает фронт)

## Анализ поиска divan.ru (2026-08-15)

Лендинг `/landing/izi` — глобальный поиск в шапке. Движок: **Diginetica AnyQuery** (`vectors_extended`). Autocomplete: `autocomplete.diginetica.net`, полная выдача: `sort.diginetica.net`. Лимит dropdown ~10 товаров; мин. ~3 символа.

**История поиска divan.ru:** Diginetica AnyQuery, `localStorage.digiHistoryStorage` (до 20 записей, дедуп по name), пишется при submit и клике в autocomplete; также `digiSearchTerm`, `digiLastSearch`, `digi_viewed_products` (40 SKU). Аналитика — `tracking.diginetica.net/event`, cookie `dSesn`. В back-dev истории нет — только глобальные frequent queries в БД.

## Импорт реестра тканей (v2, 2026-08-18)

**Файл-образец:** `Реестр_материалов_МФ_Анна_v2.xlsx`, лист «Ткани и кожа» — одна строка = один цвет коллекции (данные с строки 5).

| Колонка | Поле |
|---------|------|
| B | `material_kind` |
| C | `name` (коллекция) |
| D | `design_code` («Название» в админке) |
| E | `composition` (коллекция) |
| F/G | `price_category_id` / `price_category_line1_id` |
| H | `texture` |
| I | `color_id` (справочник, автосоздание с заглавной буквы) |
| J–M | martindale, свойства (`care_instructions`), roll_width, density (коллекция) |
| N | фото/PBR текстуры цвета → папка `fabrics` в медиатеке (всегда при импорте) |
| O | *(игнорируется — «Доступные цвета»)* |
| P | `description` (коллекция) |
| Q | `import_comment` |

- Диапазоны ценовых категорий — вкладка «Категории ткани» (не из листа «Справочники» при импорте).
- При импорте создаются недостающие категории по номеру (без лимита 8); подпись колонки F/G парсится в `PriceCategoryLabelParser` (min/max) и сохраняется в едином формате `N кат (от X до Y руб)` / `N кат (до Y руб)` в `catalog_price_categories`.
- Сертификаты удалены из импорта и админки.
- Миграции: `m260818_120000_fabric_import_v2.php`, `m260818_130000_drop_fabric_color_description.php` (убрать `description` с цвета).
- Медиа: колонка N → папка `fabrics` («Ткани») в медиатеке.
- Импорт: лоадер на форме `/admin/fabric-collection` при отправке файла; без предпросмотра — после импорта редирект на список коллекций с flash-итогом.
**Импорт тканей (2026-08-19):** исправление задеплоено (`5e62cc5`); импорт реестра v2 и клиентского шаблона работает на проде.
  - прямые URL изображений;
  - `cloud.mail.ru/public/…` (API dispatcher);
  - `disk.yandex.ru/d/{hash}/путь/к/файлу.jpg` (API: `public_key` + `path`);
  - `souz-m.ru/products/…` (og:image со страницы товара);
  - `drive.google.com/file/d/…` → прямая загрузка;
  - **не поддерживаются папки** Google Drive / Яндекс.Диск — в отчёте импорта понятное сообщение, исходная ссылка сохраняется в `source_photo_url`.
- Повторный импорт: `LocalMediaStorage::importFromPath` обновляет существующий файл по пути (не удаляет при дубликате в БД). Восстановление: `php yii fabric-registry/repair-media`.
- Список коллекций: превью цветов — hex из справочника `catalog_colors`, без загрузки фото образцов.
- Шаблон Excel для клиентов: `docs/examples/Реестр_тканей_шаблон_для_клиента.xlsx` (листы «Коллекции» + «Цвета»); пересоздать: `php scripts/generate-fabric-client-template.php`.
- **API-кэш и изображения (2026-08-18):** `ApiResponseCache` (FileCache, версионирование) — меню каталога, товары, страницы; инвалидация `ApiCacheInvalidator::touch()` из админки. `CatalogImage` в API: `src` = medium, `srcSet` {mini, medium, original}. Статика `/uploads/media/` — `Cache-Control: public, immutable` (nginx + `.htaccess`).
- **Медиатека — превью (2026-08-18):** баг в `LocalMediaStorage::importFromPath` / `replaceFromPath` — варианты генерировались, затем `unlinkPaths` удалял их (те же пути). Исправлен порядок: сначала unlink, потом generate. Восстановление: `php yii media/regenerate-variants`. `getPublicUrl` и модалка медиатеки — fallback на читаемый вариант (mini → medium → original).
- **Модалка цвета ткани:** `admin-fabric-colors.js` — при открытии редактирования плейсхолдер «Фото не выбрано» не убирался поверх превью; `setPickerValue` теперь заменяет содержимое preview целиком.

## Аудит unused DB (2026-08-19)

Проанализированы 79 миграций `m260*.php` → **39 live-таблиц** после цепочки safeUp. **Ни одна live-таблица не без использования.** Удалены миграциями (только `migrations/`): fabrics v1 (`catalog_fabrics`, `catalog_fabric_*` attributes/textures/manufacturers/images), `catalog_types`, `catalog_fabric_colors`, `catalog_product_images`, `catalog_dimension_templates`, `catalog_material_templates`. Переименовано: `catalog_groups` → `catalog_directions`.

**Колонки без кода (не migrations):** `catalog_fabric_collections.available_colors_note`; на `catalog_fabric_collection_colors`: `design_label`, `composition`, `martindale`, `properties`, `roll_width_cm`, `density_gsm` (дубли после переноса на уровень коллекции в `m260815_150000`).

**Сомнительно:** `catalog_model_fabric_collections` — junction без AR-модели (используется raw SQL); `catalog_import_media_queue` — только INSERT при пропуске URL (`MediaImportService::enqueueSkippedUrl`), SELECT нет.

## Аудит dead code (2026-08-19)

Кандидаты на удаление (консервативно): `FabricRegistryReferenceReader` (0 ссылок); views `settings/type`, `settings/group`, `settings/price-category`, `fabric-color` (модели `CatalogType`/`CatalogGroup` удалены, контроллеры — редиректы); `HelloController` (Yii scaffold); Yii scaffold `SiteController` + `views/site/*` + `models/LoginForm`/`ContactForm`. Legacy redirect-контроллеры: `SettingsType/Group/PriceCategory`, `CatalogProduct`, `FabricColor`. `CatalogImportMediaQueue` — только запись, потребителя нет. JS в `web/js/` — все 15 файлов подключены.

## ЛКД — авторизация и управление дилерами (реализовано 2026-08-20, PR-1…4)

- SMS **не используется** — только email
- Миграция `m260820_200000_dealer_auth`: `users.type/password_hash/is_blocked`, `dealer_profiles`, `dealer_activity_logs`, `dealer_credentials_log`, `orders.customer_user_id`
- Админка `/admin/user` — вкладки **Дилеры** / **Пользователи**; действия с дилером → редирект на список `?tab=dealers`
- Email: `mail/dealer/credentials-*`, `DealerCredentialsMailer`; dev — file transport (`@runtime/mail`)
- API: `POST /api/v1/dealer/auth/login|logout`, `GET|PUT /api/v1/dealer/profile`
- **Fix 2026-09-02:** `ApiController` больше не исключает `index` из `HttpBearerAuth` — иначе `GET /dealer/profile` и `/dealer/bonuses` не поднимали identity из Bearer (401 после успешного login). Публичный ping — явный except в `PingController`. Login принимает `login` как алиас `username`. **Задеплоено на dev (files).**
- **Fix 2026-09-02 CORS:** `localhost:5173`, `127.0.0.1:5173`, `https://dev.front-p-833.tw1.ru` в `corsOrigins`.
- Тесты: `tests/unit/controllers/DealerApiAuthBehaviorsTest.php`, `tests/api/DealerAuthApiTest.php` (нужна `fabrica_test` DB)
- Gate профиля: 403 `PROFILE_INCOMPLETE` для cart/orders/catalog products (dealer + Bearer)
- Аудит: login/logout, price.view, cart.add, order.create
- Авто-клиенты при заказе: `CustomerUserFactory` → вкладка «Пользователи»
- `params.dealerCabinetUrl` — ссылка в письме

## Frontend gaps — план задач (2026-09-05)

Детальный чеклист: **`memory-bank/tasks.md`** (этапы 0–6, P0–P2).  
Источник: `BACKEND_GAPS.md` (фронт МФ Анна).

Кратко:
- **P0:** nginx Authorization; каталог menu taxonomy + медиа + collections
- **P1:** заказы ЛКД (summary, specs, download, status); контракт cart attachment; Swagger sync; assigned manager
- **P2:** прайс, пароль, home products, designer auth

### Прогресс gaps (2026-09-05, сессия 2)

**Заказы ЛКД (этап 3 — частично):**
- ✅ `OrderUiMapper` — `uiStatus`, `progressStep`
- ✅ `Order::toApiSummary()` — `itemsCount`, `previewImages`
- ✅ `OrderItemApiEnricher` — `image`, `href`, `specLine1/2`, `attachment.downloadUrl`
- ✅ `GET /api/v1/orders/{number}/items/{productId}/attachment` — скачивание файла позиции
- ✅ OpenAPI: `OrderSummary`, `OrderLineItem`, `OrderResponse`, `OrderAttachmentInfo`
- ✅ Тесты: `OrderUiMapperTest`, `OrderItemApiEnricherTest`, расширен `OrderApiServiceTest` (8/8)
- ✅ `GET /api/v1/orders/{number}/documents/{id}` — скачивание документов
- ✅ `paymentMethod`, `paymentLabel`, `cashlessSurchargeAmount`, `documents[]`
- ✅ Админка: загрузка документов на странице заказа
- ✅ Swagger: `OrderDocumentInfo`, `paymentMethod` в create/view, `CartLineItem.catalogModelId`
- ✅ Swagger этап 4: CatalogProductCard/Detail, deprecated legacy, auth descriptions, openapi generate
- ✅ `assignedManager` в `GET /dealer/profile` + назначение в админке дилера
- ✅ P2: `GET /dealer/price-list`, `POST /dealer/password`, home products enricher
- ✅ Snapshot цены позиции (paidLineTotal и др.) — задеплоено на dev, Swagger 1.10.10 (2026-09-05)
- ✅ Деплой `full` на dev (2026-09-05): rsync + миграции + open-api/generate; Swagger 1.10.7 (46 paths)

**Каталог (этап 1 — частично):**
- ✅ `CatalogService::buildMenu()` — taxonomy через `CatalogTaxonomyService`, dedupe subcategories, `collectionToFrontendCard`
- ✅ `CatalogProduct::resolvePrimaryImagePayload()` — fallback image
- ✅ `getMenuProducts()` — `perPage` 1–4 (default 4)
- ✅ smoke menu на dev: `GET /catalog/menu` отдаёт taxonomy (directions/categories/subcategories)
- ⏳ тесты menu shape локально

**Fix:** восстановлен `CatalogService.php` после случайной порчи (1630 дублирующих методов удалены).

## ЛКД — промокоды и кэшбек (2026-08-22)

- **Скопировать доступ:** иконка в списке дилеров + flash-баннер с кнопкой копирования (логин/пароль/ссылка)
- **Промокоды:** VYSTAVKA; NOVINKA_{slug} — шаблон + grant в `dealer_promo_grants`; массовая выдача всем дилерам (модалка / кнопка в promo-code); ЛКД `GET /api/v1/dealer/bonuses`
- **API:** `GET /api/v1/dealer/bonuses`, `POST/DELETE /api/v1/cart/promo`, `PATCH/DELETE /api/v1/cart/cashback`
- **OpenAPI 1.5.0:** ЛКД (auth/profile/bonuses), корзина с promo/cashback, заказ с полями promo
- **Админка промокодов:** `/admin/promo-code` — список с условиями, создание custom, редактирование + блок «Условия» под формой
- **Fix 2026-09-05:** создание промокода — пустое поле «Срок действия, дней» (`default_valid_days=''`) ломало `PromoCodeForm::load()` (typed `?int`); нормализация в `beforeValidate()`, код приводится к upper case
- **Fix 2026-09-05:** код промокода — клиентская валидация требовала только `A-Z`, из‑за чего `promo2026` (нижний регистр) блокировался до отправки; паттерн `/^[A-Za-z0-9_]+$/`, на сервере `mb_strtoupper` в `beforeValidate()`
- **Promo valid_until (2026-09-05):** вместо «Срок действия, дней» — календарь «Действует до» (`valid_until` DATE); при выдаче дилеру `expires_at` = конец выбранного дня; NOVINKA по-прежнему `default_valid_days=30`
- **Promo delete (2026-09-05):** иконка удаления в списке `/admin/promo-code` только для `type=custom`; системные (VYSTAVKA, NOVINKA) не удаляются
- **Promo model scope (2026-09-05):** скидка по промокоду с `catalog_model_id` (NOVINKA и др.) считается только по позициям этой модели в корзине; без привязки — по всей корзине (VYSTAVKA, custom)
- **Promo access (2026-09-05):** промокод/кэшбек только для авторизованных дилеров с grant в «Мои бонусы»; гости и покупатели — 401 на apply, скидка 0; условия в админке обновлены
- **Promo без даты (2026-09-05):** custom без `valid_until` — действует, пока не использован (игнорируется устаревший `expires_at` grant); с `valid_until` — проверка до конца указанного дня
- **Редактирование дилера:** список выданных промокодов (статус, заказ), форма выдачи дополнительного промокода (`grant-promo`)
- **Заказ:** при оформлении дилером промокод помечается использованным (`markUsed`); в API заказа — `promo`, в админке заказа — блок скидки/промокода
- **Кэшбек:** пороги 0/1/1.5/2%, виджет в bonuses, применение в корзине (взаимоисключение с промо)
- **Cron:** `php yii cashback/accrue-monthly`, `php yii cashback/expire`
- Email/SMS уведомления по новинкам и сгоранию кэшбека — следующий этап

## План: импорт прайса моделей v2 (2026-08-27)

**Статус:** ✅ реализовано

- Миграция `m260827_120000_catalog_model_import_v2_fields`: `clearance`→`leg_height`, +`frame_spec`, `mechanism`, `filling_spec`, `additional`
- Reader: новый шаблон (мм, красивое/тех.), backward compat (legacy + клиренс)
- API OpenAPI **1.7.3**: пример ответа товара — значения-подписи с источником (SKU / модель / медиатека / ткань); без width/height и additionalProp.
- API OpenAPI **1.7.1**: русские описания SKU/`model`; в `CatalogModelSummary` добавлены `category` и `categoryLabel` (были в payload, не в схеме); поиск — `subcategoryLabel`. Eager-load `catalogModel.category` в menu-products и search.
- API OpenAPI **1.7.0**: `legHeight`, `unit:mm`, `materials.*Spec`, `fittingRoomUrl`
- Шаблон клиента обновлён в `docs/examples/Прайс_моделей_шаблон_для_клиента.xlsx`
- **Deploy:** `php yii migrate/up` ✅ применено локально 2026-08-27
- **Legacy габариты:** в БД значения вроде `42 см` — старые текстовые данные (до перехода на мм); labels обновлены, содержимое полей не конвертировалось

## Анализ API: варианты тканей на карточке товара (2026-08-25)

**Запрос:** иерархия фактура → коллекция → цвета; название+значение из справочника цветов; категория ткани на вариант.

**Текущее состояние:**
- Единственный list-endpoint: `GET /api/v1/catalog/menu/products?subcategory=` — плоский список SKU
- Нет `GET /api/v1/catalog/products/{slug}` (product detail)
- На SKU — flat `fabricColor`: id, label (design_code+catalogColor), category (номер ценовой кат.), meterPrice, hexColor, collection (name), swatches
- **Не отдаётся:** `texture` (фактура), `materialKind` (категория ткани в админке), slug коллекции, отдельные поля color из справочника, группировка
- Данные в БД есть: `CatalogFabricCollection.texture`, `material_kind`, M:N model↔collections, colors через `CatalogFabricColor` → `CatalogColor`
- Sync SKU: `CatalogModelProductSyncService` — model × linked collections × active colors

**Рекомендация:** новый/расширенный product detail + блок `fabricVariants` (grouped by texture→collection→colors); расширить `CatalogFabricColor` API payload; OpenAPI 1.7.x

## План: UX формы модели — ткани и сохранение (2026-09-02)

**Статус:** ✅ реализовано локально.

1. **Ткани:** только привязанные в списке; непривязанные в скрытом `data-fabric-pool`; добавление через select + «Добавить»; «Убрать» возвращает в pool. Partial `_fabric-group.php`.
2. **Фактура:** бейдж `admin-model-fabrics__texture` в заголовке группы; в select — `название (фактура)`.
3. **Sticky Save:** `admin-form-toolbar--sticky` в `form.php`, нижний `.admin-actions` убран.
4. **UI тканей (2026-09-02):** «Добавить ткань» внизу; карточки 2 в ряд; в варианте — справочник цвета + design_code (наименование).
5. **Цены модели (2026-09-02):** блок «Цены по категориям ткани» — `<details>`, по умолчанию закрыт; заметная иконка chevron в summary.
6. **API fittingRoomUrl (2026-09-02):** top-level `fittingRoomUrl` в `CatalogMenuProduct` / product detail (дублирует `model.fittingRoomUrl`). OpenAPI **1.10.7**.

Файлы: `form.php`, `model-fabric-products.php`, `_fabric-group.php`, `admin-model-fabrics.js`, `admin-panel.css`.

## План: импорт моделей v3 (2026-09-08, правки)

**Шаблон:** `/home/vi/Загрузки/Прайс_моделей_шаблон.xlsx`, лист «Модели».

**Колонки (смещение):** A=Active, L=Функция (для фильтра), M=Размеры спального места (Ш×Г), N+=материалы сдвинуты.

**Логика функции:** «Со спальным местом» → только диван (`sofa`); «Раскладной» → только кресло (`armchair`); «Без спального места» → no-op.

**Размеры спального места (M):** сохранять при «Со спальным местом» на диване **и** при «Раскладной» на кресле; иначе игнорировать. При смене функции — обнулять `sleeping_place_*_mm`.

**Purge:** каталог + **заказы** (`order_documents`, `order_status_log`, `order_items`, `orders`) + cart/favorites; promo/cashback FK → SET NULL.

**Деплой dev:** b63cc1e → dev.back-p-833.tw1.ru (full, +1 миграция). catalog-purge: 61 модель, 1405 товаров, 41 коллекция, заказы и т.д.

## План: collection=true — группы «линейка + N SKU» (2026-09-11)

**Терминология:** `a-plus` / `line-1` — направления; Аполлон, Артемида — линейки (`catalog_collections`, `modelLines`).

**Запрос:** `GET /api/v1/catalog/products?collection=true|false`.
- `true` → `items[]` = **массив секций** (не flat SKU): `{ slug, label, title, directionSlug, href, total, items: CatalogProductCard[] }` на линейку; пустые линейки скрывать.
- `false`/нет → `items[]` = flat `CatalogProductCard[]` (как сейчас).

**Query:** `collection=true`; `itemsPerGroup` (default **3**, max TBD); slug `collection=artemida` → scope modelLine (одна секция). Boolean vs slug — disambiguation в normalize.

**Пагинация:** `page/perPage` по **линейкам**; `meta.layout=collectionGroups`, `meta.itemsPerGroup`, `meta.total` = линеек с товарами. Фильтры/sort — до группировки; внутри линейки round-robin/sort, limit=itemsPerGroup.

**Реализовано (2026-09-11):** `collection=true` → `items[]` секции линеек; `itemsPerGroup` default 3 max 12; `meta.layout=collectionGroups`. OpenAPI **1.10.15**. **Deploy dev (2026-09-11):** commit `7cdc456` full ✅ — Swagger 1.10.15, API `collection=true` OK.

## GET /pages/partners — устаревший кэш и hero.title (2026-09-09)

**Причина:** вкладочный редактор (`tabUnifiedBlocksEditor` / home / faq) сохранял блоки без `ApiCacheInvalidator::touch()` — API отдавал старые картинки до TTL (~15 мин). `HeroBannerPayload::normalize()` не возвращал `title`/`subtitle` (только `collectionTitle`/`tagline`) — фронт по старому контракту мог брать не те поля.

**Fix:** `ApiCacheInvalidator::touch()` после save во всех unified-редакторах; alias `title`/`subtitle` в hero payload. Тесты `HeroBannerPayloadTest`, `ContentApiTest::testPagesPartnersReturnsAllBlocks`. **Deploy dev (2026-09-09):** `7596a07` files ✅.

**Аудит остальных страниц (2026-09-09):** home, designers, contacts, faq, journal, building — все активные блоки из БД отдаются в API; те же 2 бага (кэш + hero.title). Особенности: home — `journal`/`products` обогащаются; journal — `categories` + статьи; partners/designers — `mission` отдельным блоком (в админке во вкладке intro); partners — `gallery`/`terms` без UI (seed).

**Запрос «только hero» (designers/contacts/faq/building, 2026-09-09):** на dev все 4 эндпоинта отдают полный набор блоков одним GET (не только hero): designers — 6 ключей; contacts — 5; faq — 4; building — 5. Контент в блоках заполнен. После деплоя `7596a07` + bump API-кэша — `hero.title`/`hero.subtitle` на всех страницах.

## CMS: «О нас» и «Вакансии» (2026-09-09)

**Спека:** `backend-pages-about-vacancies_1.md` — 3 GET-эндпоинта + `POST /leads type=vacancy`.

**Deploy dev (2026-09-09):** `1f7c707` full ✅ — миграции vacancies + CMS about/vacancies, OpenAPI пересобран, API-кэш сброшен.
- `GET /api/v1/pages/about` — блоки seo/hero/intro/community/timeline/jobsIntro + `jobs[]` из БД
- `GET /api/v1/pages/vacancies` — hero/gallery/values + `groups[]` с jobs из БД
- `GET /api/v1/pages/vacancies/{slug}` — карточка вакансии (Swagger). Legacy: `/api/v1/vacancies/{slug}` без документации.
- `HeroBannerPayload` — алиасы `label`/`brandTitle`/`title`/`subtitle`
- Модель `Vacancy`, `VacancyService`, админка `/admin/vacancy`, страницы CMS `about`/`vacancies`
- `Lead::TYPE_VACANCY` (email обязателен, phone опционален), поля vacancy_slug/title/resume_name
- JSON fallback: `data/content/pages/about.json`, `vacancies.json`
- Миграции `m260909_193000_create_vacancies_table.php`, `m260909_200000_seed_about_vacancies_pages.php`
- Seed: `php yii seed/pages-missing` — добавляет новые JSON-страницы без пересоздания всех

**Реализовано:** `CatalogModel::collectListingSwatchesPayload()` — при ≤3 fabric-цветах: все без dedupe, `swatchCount` = их число; иначе dedupe по `catalog_color_id`, `swatchCount` = уникальных оттенков, `swatches` — до 3. OpenAPI **1.10.13** (`CatalogProductSwatch`). **Deploy dev (2026-09-09):** `437f02a` full ✅.

## Фильтр color[] — несколько цветов (анализ 2026-09-09)

**Симптом:** при выборе 2+ цветов total меньше, чем при одном цвете.

**Причина:** SQL в `CatalogProductListingService::applyFilters` корректен (`slug IN (...)` = OR). PHP/Yii получает **только последний** `color`, если фронт шлёт `color=a&color=b` без `[]`. Формат `color[]=a&color[]=b` даёт union (на dev: bezhevyy 339 + zelenyy 161 → 500). `color=a,b` → slug `a,b` → 0 товаров. То же для `texture`.

**Fix (2026-09-09):** `CatalogRequestParams` — парсинг raw query string для `color`/`texture` (repeat, `[]`, comma). Listing service использует `CatalogRequestParams::normalize()`. Тесты `CatalogRequestParamsTest`. **Deploy dev (full):** `3a43013` ✅ — миграция `m260908_150000`, OpenAPI пересобран.

## Поиск по title — token-fix + cascade (2026-09-09) ✅

**Реализовано:** `SearchTitleMatcher` — token-fix → exact title → cascade (сокращение с конца) → similar (Levenshtein) → legacy ranker fallback. Поля API: `matchType`, `matchedQuery`. Round-robin отключён для exact и cascade ≥4 слов. `correction` = token-fix, не description. Тесты `SearchTitleMatcherTest`. **Deploy dev:** `5889698` full, hotfix `c189ddf` (type relation), `836350b` (OOM vocabulary) ✅. Dev: exact title search OK.

## Поиск по наименованию модели (проверка 2026-09-09) ✅

**Dev API:** `GET /api/v1/search?q=` и `/search/products` — по названию линейки/модели работает (турин, артемида, адриано, хьюстон, манхэттен, манхэттен 1). Мин. 3 символа; кастом-SKU исключены.

**Индекс:** `SearchRanker` ищет по `title`, `collection`, `description`, `_searchText`. `toSearchApiItem()` не передаёт блок `model`, но имя коллекции/модели дублируется в SKU title (`ProductTitleBuilder`). `SearchDocumentBuilder` умеет индексировать `model.title`, если передать `model`.

**Админка:** picker (`HomePageProductsHelper::searchProducts`) явно ищет по `m.title` + title SKU + ткань/цвет.

## План: админка поиска — удаление + вкладка рекомендаций (2026-09-08) ✅

**Реализовано локально:**
- Delete query/category на `/admin/search/index`
- Вкладка «Рекомендуемые товары» (main×3, direction×2, category×2, subcategory×2 по категориям)
- Таблица `search_recommended_products`, миграция `m260908_150000` (убран `is_search_recommended`)
- `SearchRecommendedService`, picker с фильтром direction/category/subcategory
- API bootstrap: flat `recommended` + `recommendedGroups`
- OpenAPI, seed, тесты `SearchRecommendedServiceTest`
- **UI tabs (2026-09-08):** единый стиль вкладок — `AdminHtml::pageTabs()` + `admin-page-tabs` на users, fabrics, media, search, content-page, модалке медиатеки


### Параметры загрузки (форма импорта)
- Файл `.xlsx` (прайс)
- **Направление:** А+ или Линия 1 (`catalog_directions`)
- При повторном импорте — **спрашивать** (обновить / пропустить / отмена)

### Маппинг Excel → каталог
| Excel | Сущность |
|-------|----------|
| `"Артемида"`, `"Адриано"`… (строка-заголовок) | **Коллекция** (`catalog_collections`, привязка к выбранному направлению) |
| Строка с ценами (кол. B) | **Модель** (`catalog_models`) |
| Тип из B (`диван`, `кресло`…) | **Подкатегория** (нормализованное имя, см. правила) |
| Колонки C–AA (1–25 кат) | **Цены модели** (`catalog_model_prices`) — только суммы, диапазоны тканей не меняем |

### Товар по умолчанию (дополнение к sync по тканям)
- На каждую модель: 1 товар `fabric_color_id = null`
- Название: **`{Подкатегория} {Коллекция} кастом`** — напр. `Диван Артемида кастом`
- Цена default = **цена 1-й категории** ткани из матрицы модели
- Sync по коллекциям тканей × цветам — **без изменений**

### Справочник ценовых категорий
- Расширить до **25** номеров (только для матрицы цен моделей)
- Диапазоны в `catalog_price_categories` / привязках тканей **не трогаем**

### Правила категорий и подкатегорий
- Нет в справочнике → **создать** с заглавной буквы (`Диван`, `Кресло`)
- **Размеры в названии не сохраняем** (`190 см`, `115х115`, `D135` и т.п. вырезаем при нормализации)
- Строки-модули / неоднозначный тип (`Диван 190 см с бок.`, `угловая часть 115х115 см`, `круглая часть D135 см`, `Боковина`, `Модуль …`) → **категория «Модули»** (создать при отсутствии), подкатегория — базовый тип без размеров или «Модуль»
- Служебные строки без цен — пропуск

### Этапы реализации
1. ✅ Миграция `m260818_140000_catalog_price_categories_extend` (кат. 9–25)
2. ✅ `CatalogModelSpreadsheetReader`, `PriceListRowClassifier`, `CatalogModelImporter`, `CatalogModelMediaImportService`
3. ✅ CLI `php yii catalog-model/import <file> [--dryRun] [--update]` (направление из файла)
4. ✅ Default-товар в `CatalogModelProductSyncService` + `ProductTitleBuilder::buildDefault`
5. ✅ Админка: импорт на `/admin/catalog-model` (файл + confirm при конфликте)
6. ✅ Тесты `CatalogModelSpreadsheetReaderTest`, `PriceListRowClassifierTest`
7. ✅ Шаблон Excel: `docs/examples/Прайс_моделей_шаблон_для_клиента.xlsx` — лист **«Модели»** (направление, коллекция, категория, подкатегория, габариты, материалы, ткани, медиа, цены 1–25 кат); legacy «Прайс» удалён

### Исправление багов импорта (2026-08-18)
- **Slug модели:** `CatalogModel::applyTitleSlug()` — `{collection.slug}-{title}` (раньше только title → коллизия `divan` между коллекциями, импорт останавливался после первой коллекции).
- **Название SKU по ткани:** `ProductTitleBuilder::build()` — `{Тип} {Коллекция} {Цвет}`; slug товара = транслит названия (`kreslo-hyuston-belyy`), не `model.slug + color.slug`.
- **Повторный импорт:** поиск модели по `collection_id` + slug или title; CLI `php yii catalog-model/sync-products` — пересчёт slug и товаров.
- **Повторный импорт (2026-08-18):** по умолчанию конфликт = пропуск (не остановка); при создании коллекции из прайса заполняется `href`; баг `href IS NULL` блокировал все коллекции после Артемиды.

## Обзор иерархии (2026-08-14)

Цепочка: **Направление → Коллекция → Категория → Подкатегория → Модель → Фактура → Товар**

| Шаг | Админка | Статус |
|-----|---------|--------|
| Направление | Настройки → Направления | ✅ А+ / Линия 1 (миграция `m260814_160000`) |
| Коллекция | Настройки → Коллекции | ✅ направление (А+/Линия 1) + название; форма упрощена; миграция данных |
| Категория / подкатегория | Настройки → Категории | ✅ единый справочник (Диван → Прямой/Угловой…), без привязки к коллекции |
| Модель | Модели | ✅ цены: `catalog_model_price_categories`; материалы — textarea |
| Фактура / товар | Ткани + sync | ✅ как было |

## План: ЛКД — персональная скидка, прайсы, менеджеры (2026-09-10)

Запрос: детализированный план работ (без реализации).

**Реализовано локально (2026-09-10):**
1. **Две цены** — `DealerPricingService`; `retailPrice` + `dealerPrice` в каталоге, карточке, поиске, избранном, корзине, заказах. Фильтр/сортировка по `dealerPrice` при Bearer дилера.
2. **Персональная скидка** — `dealer_profiles.personal_discount_percent` (null = 0% из params); `effectiveDiscountPercent` в profile API; `dealerPrice = round(retailPrice × (1 − discount/100))`; `dealerDiscountPercent` всегда при Bearer дилера.
3. **Менеджеры** — таблица `dealer_managers` (без логина); вкладка «Менеджеры» на `/admin/user/index?tab=managers`; create/update через `/admin/dealer-manager`; inline-create в форме дилера; `assigned_manager_id` вместо `assigned_admin_id`.
4. **Прайсы** — `dealer_price_lists` (global/dealer); общий прайс — компактная загрузка иконкой на `/admin/user/index?tab=dealers`; индивидуальный на карточке дилера; `GET /dealer/price-list` → `{ common, personal }`; `GET /dealer/profile` → `priceList` (personal ?? common); OpenAPI **1.10.14**.
5. Миграция `m260910_120000_dealer_lk_enhancements` применена локально. OpenAPI обновлён (частично). Тесты: `DealerPricingServiceTest` (без БД).
6. **API цены (2026-09-10):** поле `price` / `retailPrice` — розница без изменений; добавлен только `dealerPrice`. `unitPrice` в корзине/заказе — со скидкой для дилера.
7. **Форма дилера (2026-09-10):** 3 колонки на десктопе; модалка создания менеджера; Phone → «Телефон»; поле «Тип дилера» убрано; «Менеджер фабрики» — ширина как «Телефон» (1/3 сетки); индивидуальный прайс — название, файл и кнопка в одной строке; кнопки рядом с полями — `--admin-control-height` 32px; выравнивание кнопок — `margin-top: --admin-field-label-offset`.

**Deploy dev (2026-09-10):** `full` ✅ — миграция `m260910_120000_dealer_lk_enhancements`, OpenAPI **1.10.14**, commit `9e9edf9`.

**Fix (2026-09-10):** inline-create менеджера — CSRF в fetch; `load($post, '')` ложно успешен без `DealerManager[name]` → только `load($post)`. **Deploy dev:** `files` ✅ commit `a30be30`.

**График менеджера (2026-09-10):** 4 select (дни + время) → строка `Пн–Пт 9:00–18:00` в `work_hours`; `DealerManagerWorkHoursHelper`, partial `_work_hours_fields`. **Deploy dev:** `files` ✅ commit `07e7359`.

## Админка «О нас» — вкладочный редактор (2026-09-10) ✅

**Запрос:** убрать «Год (по центру снизу)»; блок 2 — изображение + текст с абзацами; блок 3 «Галерея» — заголовок + неограниченное число фото.

**Реализовано локально:**
- Вкладки: **Главный баннер**, **Вступление**, **Галерея**, **История** (этапы + блок «Создавайте вместе с нами»).
- Типы блоков: `about_intro`, `about_gallery`, `about_timeline`; partials `_block_about_*`.
- **История:** repeatable этапы — год/период, заголовок, текст, галерея фото; снизу `jobsIntro` (заголовок + текст).
- Галереи «О нас»: community — 3 фото в строке; timeline — этап в 2 колонки (тексты слева, фото справа по 3 в строке).
- Repeatable-блоки контента: удаление иконкой корзины (`AdminHtml::repeatableRemoveButton`, `_block_row_header`).
- API timeline: `stages[]` с `year`, `label`, `title`, `text`, `images[]` (+ `image` = первое фото для совместимости).
- Скрыты из админки: `jobsIntro` (редактируется на вкладке История), `seo` (в hero).
- Миграции `m260910_140000`, `m260910_150000` — применены локально.
- Тесты: `AboutPageBlockFormBuildersTest` (4/4).

**Deploy dev (2026-09-10):** full ✅.

## Админка «Вакансии» — вкладочный редактор (2026-09-10) ✅

- Вкладки: **Главный баннер** (SEO + фото + подпись/заголовок/подзаголовок, без кнопки), **Вступление** (`values`: заголовок + фото + абзацы), **Галерея** (`slides[]`, 3 фото в строке).
- Типы: `vacancies_values`, `vacancies_gallery`; partials `_block_vacancies_*`.
- `groups` скрыты из админки (остаются в API).
- Миграция `m260910_160000_vacancies_page_admin_block_types`.
- Тесты: `VacanciesPageBlockFormBuildersTest` (2/2).
- **Вкладка «Вакансии» (`groups`):** 5 блоков (production, product_design, logistics, client_experience, management); позиции по 2 в строке; миграция `m260910_180000` (sales→client_experience, office→management).
- **API `GET /pages/about`:** все блоки страницы + `jobs[]` до 4 вакансий round-robin по группам (`VacancyAboutJobSelector`); сначала `show_on_about`, затем любые active.
- **OpenAPI:** `PageAboutResponse`, `PageVacanciesResponse`, `VacancyDetailResponse`, `VacancyGroup` (5 id), `PageAboutJob` (maxItems=4).

## Админка страниц контента — валидация сохранения (2026-09-10) ✅

- `ContentPageBlockFormValidator` + `ContentPageBlockValidators` для всех unified-редакторов: home, partners, designers, contacts, faq, journal, building, about, vacancies.
- Блокировка сохранения с flash-ошибками при тихом отбрасывании данных (коллекция/товар/фото/координаты/FAQ/вакансии и т.д.).
- `ContentPageController::processUnifiedEditorPost` — единый POST-flow; форма не сбрасывается (`formDataFromPost` для home).
- Тесты: `HomePageBlockFormValidatorTest` (8).
- **Deploy dev (2026-09-10):** `files` ✅, commit `7e2d856`.

## Корзина и оформление по сессии (2026-09-04) ✅

**Реализовано:** гостевая корзина/заказ по `X-Session-ID`; merge корзины при login (`guestSync.cart`, комментарии и attachment позиций переносятся); дилер — **comment и file на уровне позиции в корзине** (`PATCH .../comment`, `POST/DELETE/GET .../attachment`, max 1 файл, любые форматы, max 50 МБ); при `POST /orders` переносятся в позиции заказа; гость — без comment/file (403).

Миграции: `m260904_120000_cart_session_and_order_extras`, `m260905_140000_order_item_attachments`. Файлы: `CartService`, `CartController`, `OrderApiService`, `OrderAttachmentUploadService`, `GuestDataSyncService`. OpenAPI: `OrderCreateItemInput`, `OrderCreateMultipartRequest.itemAttachments`.

**Эндпоинты:** cart CRUD — optional auth; `PATCH /cart/items/{id}/comment` — dealer; `POST /cart/sync`; `POST /orders` (JSON или multipart), `GET /orders/{number}` — optional auth для гостя.
