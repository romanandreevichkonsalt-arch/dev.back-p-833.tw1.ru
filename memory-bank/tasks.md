# Gaps фронта → бэкенд (BACKEND_GAPS.md)

**Источник:** `BACKEND_GAPS.md` (2026-09-05), сверка с кодом + Swagger 1.10.7  
**Цель:** закрыть расхождения API/Swagger с UI МФ Анна (каталог, ЛКД, корзина, заказы)

---

## Уже закрыто (не включать в спринт)

- [x] Файлы заказа — только на позициях: `itemAttachments[productId]` при `POST /orders`, любые форматы, 50 МБ
- [x] Multipart в Swagger (`OrderCreateMultipartRequest`, `@OA\MediaType`)
- [x] Promo custom без `valid_until` — действует, пока не использован
- [x] Guest cart/orders по `X-Session-ID`, merge cart+favorites при login
- [x] `PATCH /cart/items/{id}/comment` (дилер)

---

## Этап 0 — Инфра P0 (DevOps, ~0.5 дн)

**Блокер:** Bearer не доходит до PHP → 401 на profile, favorites, promo.

- [ ] nginx: `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` на `dev.back-p-833.tw1.ru`
- [ ] Smoke после фикса:
  - [ ] `GET /api/v1/dealer/profile` с Bearer
  - [ ] `POST /api/v1/favorites/sync`
  - [ ] `POST /api/v1/cart/promo`

**Файлы:** конфиг nginx на сервере (не репо)

---

## Этап 1 — Каталог / меню P0 (~2–3 дн)

**Блокер UI:** пустое меню без картинок, неверная taxonomy.

### 1.1 Taxonomy меню
- [x] `GET /catalog/menu`: `groups`/`categories` = категории (Диваны, Кресла…), не коллекции A+/Линия 1
- [x] Заполнить корневой `categories[]` тем же деревом, что `navigation?direction=`
- [x] Дедупликация subcategories (дубль `kreslo` в direction)

**Файлы:** `CatalogService.php`, `CatalogUnifiedService.php`, `CatalogTaxonomyService.php`

### 1.2 Медиа каталога (системно)
- [x] Гарантировать `image.src` или `srcSet.medium` для SKU в listing (fallback через `resolvePrimaryImagePayload`)
- [x] То же для menu products и collection cards
- [ ] Проверить pipeline медиатеки / `CatalogProduct::toListingCard()`, `toMenuApiItem()`

**Файлы:** `models/CatalogProduct.php`, сервисы catalog, media

### 1.3 Карточки «Ключевые коллекции»
- [x] Расширить `CatalogFrontendCollection`: `description`, `href`, `image`/`srcSet`, `ctaLabel`, `imagePosition`, `label`, `titleUppercase`
- [x] Источник данных: админка или CMS-поля коллекций

**Файлы:** catalog services, `OpenApiSpec.php`, admin views (при необходимости)

### 1.4 Menu products
- [x] `GET /catalog/menu/products`: image, `collection`, badge (`text`+`variant`) — в `toMenuApiItem()`
- [x] Параметр `perPage` (2–4 для витрины меню) в `CatalogService::getMenuProducts`
- [x] Зафиксировать deprecated alias vs unified `/catalog/menu/{slugs}` (Swagger)

**Файлы:** `CatalogController.php`, `CatalogService.php`, routes

---

## Этап 2 — Контракт корзина / заказ P1 (~0.5–1 дн)

**Расхождение:** фронт вызывает `POST/DELETE /cart/items/{id}/attachment` — **на бэке нет**.

### Решение
- [x] **Atomic create:** `POST /orders` с `items[{productId, quantity, comment?}]` — без корзины
- [x] **Из корзины (legacy):** items без quantity — comment/attachment из корзины
- [x] Swagger 1.10.9: форматы JSON и multipart, itemAttachments[productId]

---

## Этап 3 — Заказы ЛКД P1 (~2–3 дн)

**UI:** `/account/orders`, `ORDER_BACKEND_GAPS` в `orderNormalize.js`

### 3.1 Статусы и прогресс
- [x] Маппинг бэк → UI: `new/confirmed/...` → `processing|in_work|cancelled|delivery|completed`
- [x] Поля в API: `uiStatus` или `filterStatus`, `progressStep: 1|2|3|4`
- [x] Enum статусов в Swagger (`OrderSummary`, `OrderResponse`)

**Файлы:** `models/Order.php`, `OpenApiSpec.php`

### 3.2 Список заказов (`OrderSummary`)
- [x] `itemsCount`
- [x] `previewImages[]` (1–3 превью из позиций)
- [x] `progressStep`, UI-статус

**Файлы:** `Order::toApiSummary()`, eager-load items+product media

### 3.3 Детали позиции (`OrderLineItem`)
- [x] `image` (CatalogImage)
- [x] `href` / slug для PDP
- [x] `specLine1`, `specLine2` или `attributes[]` (ткань, конфигурация)

**Файлы:** `OrderItem::toApiItem()`, обогащение при create/view

### 3.4 Детали заказа (`OrderResponse`)
- [x] `paymentMethod` / `paymentLabel`
- [x] `cashlessSurchargeAmount` (процент из `orderCashlessSurchargePercent` в params)
- [x] `documents: [{ id, label, url }]`
- [x] `progressStep`

**Файлы:** `Order` model/migration, `OrderApiService`, admin upload docs

### 3.5 Скачивание вложений (API)
- [x] `GET /api/v1/orders/{number}/items/{productId}/attachment` — Bearer, свой заказ
- [x] В ответе заказа: `attachment.downloadUrl` (опционально)
- [x] Swagger + тест

**Файлы:** `OrderController.php`, `OrderAttachmentUploadService.php`

---

## Этап 4 — Swagger sync P1 (~1 дн, параллельно с 1–3)

### Схемы runtime ↔ spec
- [x] `CartLineItem`: + `catalogModelId`
- [x] `CatalogProductCard`: + `badge`, `dealerDiscountPercent`
- [x] `CatalogProductDetailResponse`: + `modelLineSlug`, `dealerPrice`, `dealerDiscountPercent`
- [x] Удалить/deprecated: `CatalogMenuProductsResponse`, `CatalogProductsResponse` (legacy)
- [x] `OrderSummary` / `OrderLineItem` / `OrderResponse` — новые поля из этапа 3

### PathItem stubs в `OpenApiSpec.php`
- [x] `/api/v1/cart/sync` — аннотации в CartController
- [x] `/api/v1/cart/items/{productId}/comment` — аннотации в CartController
- [x] `/api/v1/catalog/menu`, `/menu/{slugs}`, `/products`, `/products/{slug}` — аннотации в CatalogController

### Описания auth
- [x] guestSync: favorites **+ cart** (AuthController, DealerAuthController descriptions)
- [x] Catalog: optional Bearer для dealer prices
- [x] Favorites: alias `page_size` в query

### Релиз docs
- [x] `php yii open-api/generate`
- [x] Деплой `full` на dev (2026-09-05): миграции payment/documents + assigned_manager, Swagger 1.10.7

---

## Этап 5 — ЛКД сервисы P1–P2 (~1–2 дн)

### P1
- [x] `assignedManager` в profile (`DealerProfileResponse.assignedManager`)
- [x] Проверка `profileComplete: true` после PUT profile (заполненные поля)

**Файлы:** `DealerProfileController`, модель assignee/manager, admin

### P2
- [x] `GET /dealer/price-list` → `{ url, label, updatedAt }` (медиатека documents или params)
- [x] `POST /dealer/password` — смена пароля
- [x] Home products ×3 с валидными image (`HomePageProductsEnricher` в PageContentService)
- [ ] Designer auth (отложено, UI заглушка)
- [ ] Leads: проверка `POST /leads` на стенде

---

## Этап 6 — Не задачи бэка (для координации)

- Фронт: подключить Contacts (`GET /pages/contacts`), HomeProducts
- Фронт: убрать `RoleDevToggle`, cart attachment (если вариант A)
- Фронт: search «Смотреть все», base64 в профиле

---

## Приоритеты (сводка)

| P | Этап | Что |
|---|------|-----|
| **P0** | 0 | nginx Authorization |
| **P0** | 1 | Меню taxonomy + медиа + collections + menu products |
| **P1** | 2 | Контракт cart attachment vs order submit |
| **P1** | 3 | Заказы: summary, line items, download, payment, docs |
| **P1** | 4 | Swagger sync |
| **P1** | 5.1 | Assigned manager |
| **P2** | 5.2 | Прайс, пароль, home, designer, leads |

---

## Быстрые win’ы (можно первыми)

1. Swagger-only: `catalogModelId`, badge, dealer fields, status enum (~2–4 ч)
2. `OrderSummary.itemsCount` + 1 preview image (~4 ч)
3. Документ для фронта: файлы только при checkout, не в cart (~0.5 ч)

---

## Компоненты

| Область | Файлы |
|---------|--------|
| Каталог | `CatalogService`, `CatalogUnifiedService`, `CatalogTaxonomyService`, `CatalogProduct` |
| Заказы | `Order`, `OrderItem`, `OrderApiService`, `OrderController` |
| Вложения | `OrderAttachmentUploadService` |
| Swagger | `docs/OpenApiSpec.php`, `controllers/api/v1/*.php` |
| ЛКД | `DealerProfileController`, новые dealer endpoints |
| Инфра | nginx на dev-сервере |

---

## Тесты (минимум по этапам)

- [x] Этап 1: unit/API catalog menu shape (`CatalogMenuShapeTest`)
- [x] Этап 3: OrderApiTest — summary fields, attachment download, status mapping
- [x] Этап 4: smoke swagger.json vs runtime samples (generate ok)
- [x] Этап 5: DealerProfileServiceTest — assignedManager, profileComplete

---

# PROMO-MKT — «Промо и акции» (admin + ЛКД)

**Старт:** 2026-09-22  
**URL админки:** `/admin/promo-code/index?tab=…` (меню: «Промо и акции»)

## Зафиксированные правила

- [x] На один SKU **не одновременно** акция и промокод; промокод не уменьшает строки под акцией.
- [x] Акция считается от **dealerLineTotal** (уменьшает эффективную дилерскую выгоду на строке).
- [x] Цены на баннере — **только вручную**, без связи с каталогом/акциями.
- [x] Промокод с баннера — **реальный** `promo_code_templates` + выдача **всем дилерам** по умолчанию.
- [x] Акции — автоматически всем дилерам в периоде действия.
- [x] ACL: CRUD — админ (`users`); использование — дилер (Bearer + `DealerAccessGuard`).

## S0 — Оболочка раздела

- [x] Пункт меню «Промо и акции», title index
- [x] Вкладки: Промокоды | Баннеры акций | Акции (`AdminHtml::pageTabs`)
- [x] Вкладка «Промокоды» — текущий список без изменений (partial)
- [x] Ссылки create/update промокода → `index?tab=promo-codes`

## S1 — Баннеры акций (админ)

- [x] Миграция `promotion_banners`
- [x] CRUD баннера: медиа, тексты, цены, CTA, попап, промокод (создать/привязать)
- [x] После save — `grantCustomTemplateToAllDealers` (идемпотентно)
- [x] Кнопка «Перевыдать всем» на карточке баннера

## S2 — API ЛКД (баннер + попап)

- [ ] `GET /api/v1/dealer/promotions/banners`
- [ ] `GET /api/v1/dealer/promotions/popup`
- [ ] OpenAPI

## S3 — Акции (админ)

- [x] Миграция `catalog_promotions`
- [x] CRUD: % или фикс, период обязателен, scope model/product

## S4 — Движок акций (корзина/каталог)

- [ ] `CatalogPromotionResolver`
- [ ] Расчёт строк + исключение promo-SKU из базы промокода
- [ ] Payload `discounts.promotion`, unit-тесты

## Компоненты

| Область | Файлы |
|---------|--------|
| Админ вкладки | `PromoSectionTabs`, `PromoCodeController`, views `promo-code/*` |
| Баннеры | `PromotionBanner`, `PromotionBannerController`, `PromotionBannerAdminService` |
| Акции | `CatalogPromotion`, `CatalogPromotionController` |
| Промо | `DealerPromoService` (mass grant custom) |
| ЛКД API | новые dealer promotion controllers |
| Корзина | `CartCheckoutService`, pricing |
