<?php

namespace app\docs;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     openapi="3.0.0",
 *     @OA\Server(
 *         url="/",
 *         description="Текущий хост API"
 *     )
 * )
 *
 * @OA\Info(
 *     title="Fabrika API",
 *     version="1.10.47",
 *     description="Документация API: страницы (вакансии — направления из БД, values.slides), каталог (retailPrice — розница, dealerPrice — скидка/акция дилера; POST /catalog/models/{slug}/3d-file — загрузка 3D-файла модели, не в карточке товара), корзина и заказы (guest → ЮKassa paymentConfirmationUrl; дилер → заявка менеджеру; retailSubtotal, dealerDiscountAmount, discounts: dealer→promo→cashback; totalAmount включает cashlessSurchargeAmount), избранное (X-Session-ID), мудборд (Bearer или X-Session-ID; GET /moodboard/public/{shareCode}), ЛКД дилера, журнал, поиск, DaData, webhook ЮKassa"
 * )
 *
 * @OA\Tag(
 *     name="Оплата",
 *     description="Webhook ЮKassa: POST /api/v1/payments/yookassa/webhook. Демо-магазин (secret test_*): GET/POST /api/v1/payments/yookassa/test/* — симуляция успеха/отказа без формы оплаты. См. docs/yookassa-testing.md."
 * )
 *
 * @OA\Tag(
 *     name="Каталог",
 *     description="GET /api/v1/catalog/menu — меню, taxonomy, навигация и листинг. GET /api/v1/catalog/products — все SKU или collection=true (секции линеек в items[]). GET /api/v1/catalog/library-products — одна SKU на коллекцию мебели (библиотека 3D). GET /api/v1/catalog/library-fabrics — только рекомендуемые ткани для страницы библиотеки (сортировка по position_number); в meta.texturesArchiveUrl — ссылка на готовый ZIP фото образцов (/files/library-fabrics.zip), если архив собран. GET /api/v1/catalog/menu/{slugs} — scope из path. HEAD — X-Total-Count. Query collection: true/false — группировка по линейкам (Аполлон, Артемида…); slug (a-plus, artemida) — scope. direction = направление. modelLine = линейка. itemsPerGroup — SKU на линейку при collection=true (default 3). GET /api/v1/catalog/products/{slug} — карточка товара."
 * )
 *
 * @OA\Tag(
 *     name="Мудборд",
 *     description="Редактор мудборда: зарегистрированный пользователь (Bearer) или гость (X-Session-ID). Picker и object-types — публичный GET без auth. CRUD /moodboard/boards: Bearer — мудборды пользователя; гость — по session_id. Гостевой мудборд получает shareCode и shareUrl; публичный просмотр GET /moodboard/public/{shareCode} без auth. После входа гостевые мудборды merge в guestSync и POST /moodboard/boards/sync (shareCode и ссылка сохраняются). User-мудборды без public_share_enabled — только автор. Обложка: multipart cover на POST/PUT, либо POST /moodboard/uploads/cover и cover.mediaId в JSON; PATCH — title. items ≤200, comments ≤100."
 * )
 *
 * @OA\Tag(
 *     name="Поиск",
 *     description="Autocomplete-поиск по каталогу (аналог dropdown divan.ru). Минимум 3 символа в параметре q. История запросов хранится на клиенте в localStorage (`SearchHistory` в `/js/search-history.js`); API history не возвращает. Блоки ответа: товары (`products`), «Часто ищут» (`oftenSearched`), «Найдено в категориях» (`categoriesFound`)."
 * )
 *
 * @OA\Tag(
 *     name="Заявки",
 *     description="POST /api/v1/leads — заявки с сайта (контакты, FAQ, партнёры, дизайнеры, вакансия). consent обязателен (true). Телефон E.164 +7… для всех типов, кроме vacancy (email обязателен). Файл attachment: PDF, Word, Excel — multipart/form-data."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sessionId",
 *     type="apiKey",
 *     in="header",
 *     name="X-Session-ID",
 *     description="ID гостевой сессии браузера. Фронт генерирует один раз (UUID) и хранит. Обязателен для гостевых запросов избранного и мудборда (CRUD /moodboard/boards). Для merge после входа передаётся вместе с Bearer на auth verify/yandex/dealer login, POST /api/v1/favorites/sync или POST /api/v1/moodboard/boards/sync."
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/request-code"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/verify-code"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/auth/yandex"
 * )
 *
 * @OA\PathItem(
 *     path="/swagger/json-schema"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/journal/articles/{slug}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/pages/journal/{slug}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/profile/me"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/leads"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/items"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/items/{productId}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/items/{productId}/comment"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/items/{productId}/attachment"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/orders"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/orders/{number}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/catalog/models/{slug}/3d-file"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dealer/auth/login"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dealer/auth/logout"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dealer/profile"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dealer/bonuses"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/promo"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/cart/cashback"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/favorites/add"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/favorites/remove"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/favorites/check"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/favorites/list"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/favorites/sync"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/bootstrap"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/categories"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/colors"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/models"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/fabrics"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/picker/surface-materials"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/object-types"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/boards"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/boards/{id}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/boards/sync"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/uploads/cover"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/moodboard/public/{code}"
 * )
 *
 * @OA\Tag(
 *     name="Корзина",
 *     description="Корзина: гость (X-Session-ID) или Bearer. CRUD позиций. Дилер: комментарий и файл к каждой позиции (PATCH .../comment, POST/DELETE/GET .../attachment), промокод и кэшбек. При оформлении заказа comment и attachment переносятся в позиции заказа."
 * )
 *
 * @OA\Tag(
 *     name="Избранное",
 *     description="Единый список избранного на пользователя (Bearer) или гостевую сессию (X-Session-ID / sessionId). После входа merge через guestSync в auth-ответе и/или POST /api/v1/favorites/sync (идемпотентно)."
 * )
 *
 * @OA\Tag(
 *     name="Заказы",
 *     description="Оформление заказа из корзины и просмотр списка/деталей заказов; при оформлении дилером применённый промокод помечается использованным (Bearer token)"
 * )
 *
 * @OA\Tag(
 *     name="ЛКД — авторизация",
 *     description="Вход и выход дилера в личном кабинете (логин/пароль, Bearer token)"
 * )
 *
 * @OA\Tag(
 *     name="ЛКД — профиль",
 *     description="Профиль дилера: просмотр и заполнение обязательных полей для доступа к каталогу и заказам"
 * )
 *
 * @OA\Tag(
 *     name="ЛКД — бонусы",
 *     description="Промокоды и кэшбек дилера"
 * )
 *
 * @OA\Tag(
 *     name="DaData",
 *     description="Подсказки адреса через DaData: POST /api/v1/dadata/suggest/city — населённый пункт; POST /api/v1/dadata/suggest/address — полный адрес. Без DADATA_API_KEY — mock-данные."
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dadata/suggest/city"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/dadata/suggest/address"
 * )
 *
 * @OA\PathItem(
 *     path="/api/v1/{path}"
 * )
 *
 * @OA\PathItem(
 *     path="/admin/default/index"
 * )
 *
 * @OA\Schema(
 *     schema="AuthRequestCodeInput",
 *     type="object",
 *     required={"phone"},
 *     @OA\Property(property="phone", type="string", example="79998886644")
 * )
 *
 * @OA\Schema(
 *     schema="AuthRequestCodeOutput",
 *     type="object",
 *     required={"ok","expires_in"},
 *     @OA\Property(property="ok", type="boolean", example=true),
 *     @OA\Property(property="expires_in", type="integer", example=60)
 * )
 *
 * @OA\Schema(
 *     schema="AuthVerifyCodeInput",
 *     type="object",
 *     required={"phone","code"},
 *     @OA\Property(property="phone", type="string", example="79998886644"),
 *     @OA\Property(property="code", type="string", example="1234"),
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true, description="Гостевая сессия для merge избранного; альтернатива — заголовок X-Session-ID")
 * )
 *
 * @OA\Schema(
 *     schema="AuthYandexInput",
 *     type="object",
 *     required={"access_token"},
 *     @OA\Property(property="access_token", type="string", example="y0_AgAAA..."),
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AuthVerifyCodeOutput",
 *     type="object",
 *     required={"token_type","access_token","expires_in","guestSync"},
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="access_token", type="string", example="z4aMeWg3Hf..."),
 *     @OA\Property(property="expires_in", type="integer", example=2592000),
 *     @OA\Property(property="guestSync", ref="#/components/schemas/GuestSyncResponse")
 * )
 *
 * @OA\Schema(
 *     schema="PingResponse",
 *     type="object",
 *     required={"status","service","version"},
 *     @OA\Property(property="status", type="string", example="ok"),
 *     @OA\Property(property="service", type="string", example="fabrika-backend-api"),
 *     @OA\Property(property="version", type="string", example="v1")
 * )
 *
 * @OA\Schema(
 *     schema="ProfileMeResponse",
 *     type="object",
 *     required={"id","name","phone","email","avatar","subscription"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", nullable=true, example="Иван"),
 *     @OA\Property(property="phone", type="string", example="79998886644"),
 *     @OA\Property(property="email", type="string", nullable=true, example="user@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true, example=null),
 *     @OA\Property(property="subscription", type="boolean", example=false, description="Подписка на персональные предложения и новости компании")
 * )
 *
 * @OA\Schema(
 *     schema="ProfileSubscriptionRequest",
 *     type="object",
 *     required={"subscription"},
 *     @OA\Property(property="subscription", type="boolean", example=true, description="true — подписаться, false — отписаться")
 * )
 *
 * @OA\Schema(
 *     schema="ProfileSubscriptionResponse",
 *     type="object",
 *     required={"subscription"},
 *     @OA\Property(property="subscription", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogImageSrcSet",
 *     type="object",
 *     description="Варианты одного изображения из медиатеки. mini — до 200px, medium — до 1200px, large — до 1920px (баннеры/CMS), original — исходный файл.",
 *     required={"mini","medium","original"},
 *     example={
 *         "mini"="медиатека · URL миниатюры (до 200px)",
 *         "medium"="медиатека · URL medium (до 1200px)",
 *         "large"="медиатека · URL large (до 1920px)",
 *         "original"="медиатека · URL оригинала"
 *     },
 *     @OA\Property(property="mini", type="string", example="медиатека · URL миниатюры (до 200px)", title="миниатюра", description="миниатюра из медиатеки, max-width 200px"),
 *     @OA\Property(property="medium", type="string", example="медиатека · URL medium (до 1200px)", title="основной вариант", description="основной вариант из медиатеки, max-width 1200px"),
 *     @OA\Property(property="large", type="string", nullable=true, example="медиатека · URL large (до 1920px)", title="large", description="large для баннеров/CMS, max-width 1920px, WebP q92"),
 *     @OA\Property(property="original", type="string", example="медиатека · URL оригинала", title="оригинал", description="оригинал из медиатеки без ресайза")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogImage",
 *     type="object",
 *     description="Изображение из медиатеки. Листинг/поиск: src и srcSet.medium|mini — кадр редактора (_listing_m/_listing_s). Карточка товара: src и srcSet — auto mini/medium/large (_s/_m/_l), без кадра редактора; original в srcSet не отдаётся. width/height в ответе есть, в примере опущены.",
 *     required={"src","alt"},
 *     example={
 *         "src"="медиатека · URL medium (до 1200px)",
 *         "alt"="медиатека · альтернативный текст",
 *         "srcSet"={
 *             "mini"="медиатека · URL миниатюры кадра каталога (до 200px)",
 *             "medium"="медиатека · URL medium (до 1200px) или кадра каталога (листинг)"
 *         }
 *     },
 *     @OA\Property(property="src", type="string", example="медиатека · URL medium (до 1200px)", title="URL medium", description="листинг/поиск — кадр каталога; карточка товара — auto _m"),
 *     @OA\Property(property="alt", type="string", example="медиатека · альтернативный текст", title="альтернативный текст", description="альтернативный текст изображения"),
 *     @OA\Property(
 *         property="srcSet",
 *         nullable=true,
 *         title="набор размеров",
 *         description="варианты файла из медиатеки: mini, medium, original",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogImageSrcSet")}
 *     ),
 *     @OA\Property(property="width", type="integer", nullable=true, title="ширина", description="ширина оригинала, px; в примере ответа не показываем"),
 *     @OA\Property(property="height", type="integer", nullable=true, title="высота", description="высота оригинала, px; в примере ответа не показываем")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFabricColor",
 *     type="object",
 *     description="Цвет ткани SKU: коллекция тканей × цвет из справочника. swatches — фото/PBR из медиатеки; hexColor — catalog_colors.",
 *     required={"id","label","swatches"},
 *     example={
 *         "id"="ткань · slug цвета (design_code)",
 *         "label"="GUCCI 422",
 *         "colorName"="Бежевый",
 *         "category"=1,
 *         "meterPrice"="ткань · цена погонного метра коллекции",
 *         "hexColor"="справочник цветов · HEX",
 *         "collection"="ткань · название коллекции ткани",
 *         "swatches"={
 *             {
 *                 "src"="ткань · образец, URL medium из медиатеки",
 *                 "alt"="медиатека · альтернативный текст",
 *                 "srcSet"={
 *                     "mini"="медиатека · URL миниатюры (до 200px)",
 *                     "medium"="медиатека · URL medium (до 1200px)",
 *                     "large"="медиатека · URL large (до 1920px)"
 *                 }
 *             }
 *         }
 *     },
 *     @OA\Property(property="id", type="string", description="slug цвета ткани (design_code)", example="ткань · slug цвета (design_code)", title="slug цвета"),
 *     @OA\Property(property="label", type="string", example="GUCCI 422", description="название коллекции ткани и design-code цвета через пробел", title="название цвета"),
 *     @OA\Property(property="colorName", type="string", nullable=true, example="Бежевый", title="название цвета", description="русское название из справочника catalog_colors"),
 *     @OA\Property(property="colorId", type="string", nullable=true, example="справочник цветов · slug", title="slug глобального цвета", description="slug из catalog_colors для фильтра color"),
 *     @OA\Property(property="category", type="integer", nullable=true, minimum=1, title="ценовая категория", description="номер ценовой категории коллекции ткани"),
 *     @OA\Property(property="meterPrice", type="string", nullable=true, example="ткань · цена погонного метра коллекции", title="цена за метр", description="стоимость погонного метра коллекции ткани"),
 *     @OA\Property(property="hexColor", type="string", nullable=true, example="справочник цветов · HEX", title="HEX цвета", description="HEX из справочника catalog_colors"),
 *     @OA\Property(property="collection", type="string", nullable=true, description="название коллекции ткани", example="ткань · название коллекции ткани", title="коллекция ткани"),
 *     @OA\Property(property="texture", type="string", nullable=true, example="ткань · фактура", title="фактура", description="фактура коллекции ткани (catalog_fabric_collections.texture)"),
 *     @OA\Property(property="isRecommendedFabric", type="boolean", description="Реком. ткань из реестра (колонка O)"),
 *     @OA\Property(property="positionNumber", type="integer", nullable=true, description="№ позиции в коллекции (колонка P)"),
 *     @OA\Property(property="description", type="string", nullable=true, description="Описание цветодизайна для карточки товара"),
 *     @OA\Property(
 *         property="swatches",
 *         type="array",
 *         description="Фото/PBR-текстуры цвета (swatch + доп. ракурсы); каждый элемент — CatalogImage с srcSet",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFabricColorOption",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CatalogFabricColor"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="productSlug", type="string", nullable=true, description="slug SKU модели в выбранном цвете"),
 *             @OA\Property(property="href", type="string", nullable=true, example="/product/divan-artemida-belyy", description="ссылка на карточку SKU")
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFabricCollectionVariant",
 *     type="object",
 *     required={"id","name","colors"},
 *     @OA\Property(property="id", type="string", description="slug коллекции ткани"),
 *     @OA\Property(property="name", type="string", description="название коллекции ткани"),
 *     @OA\Property(property="category", type="integer", nullable=true, description="номер ценовой категории"),
 *     @OA\Property(property="price", type="string", nullable=true, description="Отформатированная розничная цена модели в категории коллекции (из матрицы)"),
 *     @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога (price_amount)"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции (только Bearer дилера)"),
 *     @OA\Property(
 *         property="colors",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CatalogFabricColorOption")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFabricVariantGroup",
 *     type="object",
 *     required={"texture","collections"},
 *     @OA\Property(property="texture", type="string", description="фактура (catalog_fabric_collections.texture)"),
 *     @OA\Property(
 *         property="collections",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CatalogFabricCollectionVariant")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogVideo",
 *     type="object",
 *     description="Видео: у SKU из модели берётся видео модели, иначе видео товара.",
 *     required={"src","mime"},
 *     example={
 *         "src"="модель · URL видео (если нет — с SKU)",
 *         "mime"="модель · MIME видео"
 *     },
 *     @OA\Property(property="src", type="string", example="модель · URL видео (если нет — с SKU)", description="публичный URL видео из медиатеки", title="URL видео"),
 *     @OA\Property(property="mime", type="string", example="модель · MIME видео", description="MIME-тип, например video/mp4", title="MIME-тип")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogBadge",
 *     type="object",
 *     description="Бейдж (хит, новинка, promotion — «Акция»). image — из медиатеки, если задана.",
 *     required={"text","variant"},
 *     example={
 *         "text"="бейдж · текст",
 *         "variant"="бейдж · вариант оформления",
 *         "image"={
 *             "src"="бейдж · URL medium из медиатеки",
 *             "alt"="медиатека · альтернативный текст",
 *             "srcSet"={
 *                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                 "medium"="медиатека · URL medium (до 1200px)",
 *                 "original"="медиатека · URL оригинала"
 *             }
 *         }
 *     },
 *     @OA\Property(property="text", type="string", example="бейдж · текст", description="текст бейджа", title="текст"),
 *     @OA\Property(property="variant", type="string", example="бейдж · вариант оформления", description="вариант оформления (hit, new, promotion и др.)", title="вариант"),
 *     @OA\Property(
 *         property="image",
 *         nullable=true,
 *         title="картинка бейджа",
 *         description="картинка бейджа, если задана",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogImage")}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogPromotionActive",
 *     type="object",
 *     description="Активная акция каталога (catalog_promotions). Только при Bearer дилера; цена считается на лету.",
 *     required={"id","title","discountType","discountValue","endsAt"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="title", type="string", example="Осеняя распродажа"),
 *     @OA\Property(property="discountType", type="string", enum={"percent","fixed_amount"}, description="percent — % от розницы; fixed_amount — сумма (₽), вычитается из розницы"),
 *     @OA\Property(property="discountValue", type="number", format="float", example=15),
 *     @OA\Property(property="endsAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogDimensions",
 *     type="object",
 *     description="Габариты с формы модели, мм. Если пусто — null.",
 *     example={
 *         "overallSize"="модель · габарит Ш×В×Г, мм",
 *         "seatDepth"="модель · глубина сиденья, мм",
 *         "seatHeight"="модель · высота сиденья, мм",
 *         "armrestWidth"="модель · ширина подлокотника, мм",
 *         "legHeight"="модель · высота опоры, мм",
 *         "unit"="модель · единица (всегда mm)"
 *     },
 *     @OA\Property(property="overallSize", type="string", nullable=true, example="модель · габарит Ш×В×Г, мм", title="габарит Ш×В×Г", description="габарит Ш×В×Г, мм"),
 *     @OA\Property(property="seatDepth", type="string", nullable=true, example="модель · глубина сиденья, мм", title="глубина сиденья", description="глубина посадочного места, мм"),
 *     @OA\Property(property="seatHeight", type="string", nullable=true, example="модель · высота сиденья, мм", title="высота сиденья", description="высота посадочного места, мм"),
 *     @OA\Property(property="armrestWidth", type="string", nullable=true, example="модель · ширина подлокотника, мм", title="ширина подлокотника", description="ширина подлокотника, мм"),
 *     @OA\Property(property="legHeight", type="string", nullable=true, example="модель · высота опоры, мм", title="высота опоры", description="высота опоры, мм"),
 *     @OA\Property(property="width", type="integer", nullable=true, example=2500, title="ширина, мм", description="ширина (число), мм — для фильтра"),
 *     @OA\Property(property="height", type="integer", nullable=true, example=860, title="высота, мм", description="высота (число), мм — для фильтра"),
 *     @OA\Property(property="depth", type="integer", nullable=true, example=1200, title="глубина, мм", description="глубина (число), мм — для фильтра"),
 *     @OA\Property(property="cornerDepth", type="integer", nullable=true, example=900, title="глубина угла, мм", description="глубина стороны угла (4-е значение в overallSize), мм"),
 *     @OA\Property(property="unit", type="string", example="модель · единица (всегда mm)", title="единица", description="единица измерения: всегда mm")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMaterials",
 *     type="object",
 *     description="Материалы с формы модели: *Spec — технические, остальные — для карточки. Если пусто — null.",
 *     example={
 *         "frameSpec"="модель · каркас, тех. описание",
 *         "mechanism"="модель · механизм",
 *         "fillingSpec"="модель · наполнение, тех. описание",
 *         "additional"="модель · дополнительно",
 *         "frame"="модель · каркас для карточки",
 *         "foundation"="модель · основание для карточки",
 *         "filling"="модель · наполнение для карточки",
 *         "upholstery"="модель · обивка для карточки",
 *         "supports"="модель · опоры для карточки"
 *     },
 *     @OA\Property(property="frameSpec", type="string", nullable=true, example="модель · каркас, тех. описание", title="каркас (тех.)", description="каркас, техническое описание"),
 *     @OA\Property(property="mechanism", type="string", nullable=true, example="модель · механизм", title="механизм", description="механизм трансформации"),
 *     @OA\Property(property="fillingSpec", type="string", nullable=true, example="модель · наполнение, тех. описание", title="наполнение (тех.)", description="наполнение, техническое описание"),
 *     @OA\Property(property="additional", type="string", nullable=true, example="модель · дополнительно", title="дополнительно", description="дополнительно"),
 *     @OA\Property(property="frame", type="string", nullable=true, example="модель · каркас для карточки", title="каркас", description="каркас для карточки (красивое)"),
 *     @OA\Property(property="foundation", type="string", nullable=true, example="модель · основание для карточки", title="основание", description="основание для карточки (красивое)"),
 *     @OA\Property(property="filling", type="string", nullable=true, example="модель · наполнение для карточки", title="наполнение", description="наполнение для карточки (красивое)"),
 *     @OA\Property(property="upholstery", type="string", nullable=true, example="модель · обивка для карточки", title="обивка", description="обивка для карточки (красивое)"),
 *     @OA\Property(property="supports", type="string", nullable=true, example="модель · опоры для карточки", title="опоры", description="опоры для карточки (красивое)")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogModelPrices",
 *     type="object",
 *     description="Матрица цен модели: ключ — номер категории ткани 1–25, значение — цена. Пустые категории не отдаются.",
 *     example={
 *         "1"="модель · цена 1-й категории ткани",
 *         "2"="модель · цена 2-й категории ткани",
 *         "25"="модель · цена 25-й категории ткани"
 *     },
 *     @OA\Property(property="1", type="string", example="модель · цена 1-й категории ткани", description="цена 1-й категории ткани"),
 *     @OA\Property(property="2", type="string", example="модель · цена 2-й категории ткани", description="цена 2-й категории ткани"),
 *     @OA\Property(property="25", type="string", example="модель · цена 25-й категории ткани", description="цена 25-й категории ткани")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogModel3dFileUploadResponse",
 *     type="object",
 *     description="Результат загрузки 3D-файла модели. Поля polygons_3d / file_3d_url / file_3d не входят в карточку товара.",
 *     required={"modelSlug","fileUrl","mediaId","filename"},
 *     @OA\Property(property="modelSlug", type="string", example="pryamoy-divan-adriano"),
 *     @OA\Property(property="sourceUrl", type="string", nullable=true, example="https://example.com/model.glb"),
 *     @OA\Property(property="fileUrl", type="string", example="/uploads/media/models-3d/2026/09/media_….glb"),
 *     @OA\Property(property="mediaId", type="integer", example=42),
 *     @OA\Property(property="filename", type="string", example="adriano.glb")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogModelSummary",
 *     type="object",
 *     description="Полные данные модели (`CatalogModel::toCatalogApiPayload`). Приходят в поле `model` у SKU, сгенерированного из модели. Содержит поля формы модели в админке: иерархия (коллекция, категория, подкатегория), габариты, материалы, медиа (ракурсы, интерьер, тех.фото, видео), бейдж, ссылка на 3D-примерочную, ссылка на облачную папку с тех.фото (если файлы не загружены в медиатеку) и матрицу цен 1–25 кат. Не отдаются: направление, список коллекций тканей, активность и порядок сортировки, полигоны для 3D и файл/ссылка 3D (загрузка — POST /catalog/models/{slug}/3d-file).",
 *     required={"id","title"},
 *     @OA\Property(property="id", type="string", example="модель · slug", title="slug модели", description="slug модели"),
 *     @OA\Property(property="title", type="string", example="модель · название", title="название", description="название модели"),
 *     @OA\Property(property="subtitle", type="string", nullable=true, example="модель · подзаголовок", title="подзаголовок", description="подзаголовок"),
 *     @OA\Property(property="description", type="string", nullable=true, example="модель · описание", title="описание", description="описание"),
 *     @OA\Property(property="type", type="string", nullable=true, example="модель · название подкатегории (legacy)", title="подкатегория", description="название подкатегории (label), legacy-поле"),
 *     @OA\Property(property="typeId", type="string", nullable=true, example="модель · slug подкатегории (legacy)", title="slug подкатегории (legacy)", description="slug подкатегории, legacy-поле (дублирует subcategory)"),
 *     @OA\Property(property="category", type="string", nullable=true, example="модель · slug категории", title="slug категории", description="slug категории (например divan)"),
 *     @OA\Property(property="categoryLabel", type="string", nullable=true, example="модель · название категории", title="название категории", description="название категории (например Диван)"),
 *     @OA\Property(property="subcategory", type="string", nullable=true, example="модель · slug подкатегории", title="slug подкатегории", description="slug подкатегории"),
 *     @OA\Property(property="collection", type="string", nullable=true, example="модель · коллекция мебели", title="коллекция", description="название коллекции мебели"),
 *     @OA\Property(property="customProductSlug", type="string", nullable=true, example="pryamoy-divan-adriano-kastom", title="slug кастом-SKU", description="slug кастомного товара модели (SKU без выбранного цвета ткани); null, если кастом-SKU не создан"),
 *     @OA\Property(property="layout", type="string", nullable=true, example="модель · раскладка карточки", title="раскладка", description="slug раскладки карточки: featured, stacked или compact"),
 *     @OA\Property(
 *         property="badge",
 *         nullable=true,
 *         title="бейдж",
 *         description="бейдж модели",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogBadge")}
 *     ),
 *     @OA\Property(
 *         property="video",
 *         nullable=true,
 *         title="видео",
 *         description="видео модели",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogVideo")}
 *     ),
 *     @OA\Property(property="fittingRoomUrl", type="string", nullable=true, example="модель · ссылка на 3D-примерочную", title="3D-примерочная", description="ссылка на 3D-примерочную"),
 *     @OA\Property(
 *         property="dimensions",
 *         nullable=true,
 *         title="габариты",
 *         description="габариты, мм; null если не заполнены",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogDimensions")}
 *     ),
 *     @OA\Property(
 *         property="materials",
 *         nullable=true,
 *         title="материалы",
 *         description="материалы; null если не заполнены",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogMaterials")}
 *     ),
 *     @OA\Property(
 *         property="modelImages",
 *         type="array",
 *         title="ракурсы",
 *         description="фото модели (ракурсы); пустой массив, если фото нет",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="modelInteriorImages",
 *         type="array",
 *         nullable=true,
 *         title="фото в интерьере",
 *         description="фото модели в интерьере; null, если галерея пустая",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="dimensionImages",
 *         type="array",
 *         nullable=true,
 *         title="тех. фото габаритов",
 *         description="загруженные тех. фото габаритов (медиатека); null, если галерея пустая. При импорте из прайса прямые URL сохраняются здесь; ссылка на папку (Google Drive и т.п.) — в techPhotosFolderUrl",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="techPhotosFolderUrl",
 *         type="string",
 *         nullable=true,
 *         example="https://drive.google.com/drive/folders/…",
 *         title="папка с тех.фото",
 *         description="внешняя ссылка на облачную папку с тех. фото габаритов (админка «Ссылка на диск с тех.фото»); null, если заданы только dimensionImages или поле не заполнено"
 *     ),
 *     @OA\Property(
 *         property="prices",
 *         nullable=true,
 *         title="матрица цен",
 *         description="матрица цен по категориям ткани 1–25; null, если цен нет",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogModelPrices")}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuProduct",
 *     type="object",
 *     description="Товар (SKU): модель + цвет ткани или «кастом» без цвета. Отдаётся в GET /api/v1/catalog/menu/products. Поля `modelId`, `model` и галереи модели есть только у SKU, сгенерированных из модели. `fabricColor` есть только при выбранном цвете ткани.",
 *     required={"id","title","href","image"},
 *     @OA\Property(property="id", type="string", example="SKU · slug товара", title="slug товара", description="slug товара (SKU)"),
 *     @OA\Property(property="title", type="string", example="SKU · название", title="название", description="название товара"),
 *     @OA\Property(property="subtitle", type="string", nullable=true, example="SKU · подзаголовок", title="подзаголовок", description="подзаголовок"),
 *     @OA\Property(property="description", type="string", nullable=true, example="SKU · описание", title="описание", description="описание"),
 *     @OA\Property(property="type", type="string", nullable=true, example="SKU · название подкатегории (legacy)", title="подкатегория", description="название подкатегории (label), legacy-поле"),
 *     @OA\Property(property="collection", type="string", nullable=true, example="SKU · коллекция мебели", title="коллекция", description="название коллекции мебели"),
 *     @OA\Property(property="subcategory", type="string", nullable=true, example="SKU · slug подкатегории", title="slug подкатегории", description="slug подкатегории"),
 *     @OA\Property(property="href", type="string", example="SKU · ссылка на карточку", title="ссылка", description="ссылка на карточку товара"),
 *     @OA\Property(property="price", type="string", nullable=true, example="SKU · цена (кат. ткани цвета; кастом — 1 кат.)", title="цена", description="Deprecated: дублирует priceDisplay (розница каталога). Основное поле — retailPrice."),
 *     @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога (price_amount)"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции (только Bearer дилера)"),
 *     @OA\Property(property="priceDisplay", type="string", nullable=true, description="Отформатированная розничная цена каталога (для зачёркнутой цены в UI)"),
 *     @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="effectiveDiscountPercent дилера, %; omit при активной акции каталога"),
 *     @OA\Property(property="catalogPromotion", ref="#/components/schemas/CatalogPromotionActive", nullable=true, description="Активная акция на SKU/модель (только дилер)"),
 *     @OA\Property(
 *         property="image",
 *         title="фото SKU",
 *         description="фото SKU для карточки каталога; если файла нет — src пустая строка",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogImage")}
 *     ),
 *     @OA\Property(
 *         property="video",
 *         nullable=true,
 *         title="видео",
 *         description="видео: с модели, иначе с товара",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogVideo")}
 *     ),
 *     @OA\Property(property="imagePosition", type="string", example="SKU · позиция картинки", title="позиция картинки", description="позиция картинки (по умолчанию center)"),
 *     @OA\Property(
 *         property="badge",
 *         nullable=true,
 *         title="бейдж",
 *         description="Первый бейдж из badges (совместимость)",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogBadge")}
 *     ),
 *     @OA\Property(
 *         property="badges",
 *         type="array",
 *         nullable=true,
 *         description="Бейджи для дилера: при акции первым «Акция» (variant=promotion), затем хит/новинка",
 *         @OA\Items(ref="#/components/schemas/CatalogBadge")
 *     ),
 *     @OA\Property(property="layout", type="string", nullable=true, example="SKU · раскладка карточки", title="раскладка", description="slug раскладки карточки"),
 *     @OA\Property(
 *         property="dimensions",
 *         nullable=true,
 *         title="габариты",
 *         description="габариты (с модели, если SKU из модели)",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogDimensions")}
 *     ),
 *     @OA\Property(
 *         property="materials",
 *         nullable=true,
 *         title="материалы",
 *         description="материалы (с модели, если SKU из модели)",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogMaterials")}
 *     ),
 *     @OA\Property(property="quantity", type="integer", example=2, title="остаток", description="остаток на складе"),
 *     @OA\Property(property="inStock", type="boolean", example=true, title="в наличии", description="true, если товар активен и quantity > 0"),
 *     @OA\Property(property="custom", type="boolean", example=false, title="кастом", description="true — SKU «кастом» модели без выбранного цвета ткани"),
 *     @OA\Property(property="fittingRoomUrl", type="string", nullable=true, example="SKU · ссылка на 3D-примерочную", title="3D-примерочная", description="ссылка на 3D-примерочную с модели; только у SKU, сгенерированных из модели"),
 *     @OA\Property(property="modelId", type="string", nullable=true, example="модель · slug", title="slug модели", description="slug модели; только у SKU, сгенерированных из модели"),
 *     @OA\Property(
 *         property="model",
 *         nullable=true,
 *         title="модель",
 *         description="полные данные модели; только у SKU, сгенерированных из модели",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogModelSummary")}
 *     ),
 *     @OA\Property(
 *         property="modelImages",
 *         type="array",
 *         nullable=true,
 *         title="ракурсы модели",
 *         description="ракурсы модели; поле есть только у SKU из модели (может быть пустым массивом)",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="modelInteriorImages",
 *         type="array",
 *         nullable=true,
 *         title="фото в интерьере",
 *         description="фото модели в интерьере; поле есть, только если галерея не пустая",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="dimensionImages",
 *         type="array",
 *         nullable=true,
 *         title="тех. фото габаритов",
 *         description="загруженные тех. фото габаритов (медиатека); поле есть, только если галерея не пустая. Если файлы на облачном диске — см. techPhotosFolderUrl",
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(
 *         property="techPhotosFolderUrl",
 *         type="string",
 *         nullable=true,
 *         example="https://drive.google.com/drive/folders/…",
 *         title="папка с тех.фото",
 *         description="внешняя ссылка на облачную папку с тех. фото габаритов (админка модели «Ссылка на диск с тех.фото»); дублирует model.techPhotosFolderUrl; только у SKU из модели"
 *     ),
 *     @OA\Property(
 *         property="fabricColor",
 *         nullable=true,
 *         title="цвет ткани",
 *         description="цвет ткани SKU; поля нет у «кастом»",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogFabricColor")}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuProductsResponse",
 *     type="object",
 *     deprecated=true,
 *     description="Legacy alias GET /api/v1/catalog/menu/products. Используйте GET /api/v1/catalog/menu с query subcategory или path /catalog/menu/{slugs}.",
 *     required={"items"},
 *     example={
 *         "items"={
 *             {
 *                 "id"="SKU · slug товара",
 *                 "title"="SKU · название",
 *                 "subtitle"="SKU · подзаголовок",
 *                 "description"="SKU · описание",
 *                 "type"="SKU · название подкатегории (legacy)",
 *                 "collection"="SKU · коллекция мебели",
 *                 "subcategory"="SKU · slug подкатегории",
 *                 "href"="SKU · ссылка на карточку",
 *                 "price"="SKU · цена (кат. ткани цвета; кастом — 1 кат.)",
 *                 "image"={
 *                     "src"="SKU · фото карточки, URL medium из медиатеки",
 *                     "alt"="медиатека · альтернативный текст",
 *                     "srcSet"={
 *                         "mini"="медиатека · URL миниатюры (до 200px)",
 *                         "medium"="медиатека · URL medium (до 1200px)",
 *                         "original"="медиатека · URL оригинала"
 *                     }
 *                 },
 *                 "video"={
 *                     "src"="модель · URL видео (если нет — с SKU)",
 *                     "mime"="модель · MIME видео"
 *                 },
 *                 "imagePosition"="SKU · позиция картинки",
 *                 "badge"={
 *                     "text"="SKU · текст бейджа",
 *                     "variant"="SKU · вариант оформления бейджа",
 *                     "image"={
 *                         "src"="бейдж · URL medium из медиатеки",
 *                         "alt"="медиатека · альтернативный текст",
 *                         "srcSet"={
 *                             "mini"="медиатека · URL миниатюры (до 200px)",
 *                             "medium"="медиатека · URL medium (до 1200px)",
 *                             "original"="медиатека · URL оригинала"
 *                         }
 *                     }
 *                 },
 *                 "layout"="SKU · раскладка карточки",
 *                 "dimensions"={
 *                     "overallSize"="модель · габарит Ш×В×Г, мм",
 *                     "seatDepth"="модель · глубина сиденья, мм",
 *                     "seatHeight"="модель · высота сиденья, мм",
 *                     "armrestWidth"="модель · ширина подлокотника, мм",
 *                     "legHeight"="модель · высота опоры, мм",
 *                     "unit"="модель · единица (всегда mm)"
 *                 },
 *                 "materials"={
 *                     "frameSpec"="модель · каркас, тех. описание",
 *                     "mechanism"="модель · механизм",
 *                     "fillingSpec"="модель · наполнение, тех. описание",
 *                     "additional"="модель · дополнительно",
 *                     "frame"="модель · каркас для карточки",
 *                     "foundation"="модель · основание для карточки",
 *                     "filling"="модель · наполнение для карточки",
 *                     "upholstery"="модель · обивка для карточки",
 *                     "supports"="модель · опоры для карточки"
 *                 },
 *                 "quantity"=2,
 *                 "inStock"=true,
 *                 "custom"=false,
 *                 "fittingRoomUrl"="SKU · ссылка на 3D-примерочную",
 *                 "modelId"="модель · slug",
 *                 "model"={
 *                     "id"="модель · slug",
 *                     "title"="модель · название",
 *                     "subtitle"="модель · подзаголовок",
 *                     "description"="модель · описание",
 *                     "type"="модель · название подкатегории (legacy)",
 *                     "typeId"="модель · slug подкатегории (legacy)",
 *                     "category"="модель · slug категории",
 *                     "categoryLabel"="модель · название категории",
 *                     "subcategory"="модель · slug подкатегории",
 *                     "collection"="модель · коллекция мебели",
 *                     "layout"="модель · раскладка карточки",
 *                     "badge"={
 *                         "text"="модель · текст бейджа",
 *                         "variant"="модель · вариант оформления бейджа",
 *                         "image"={
 *                             "src"="бейдж · URL medium из медиатеки",
 *                             "alt"="медиатека · альтернативный текст",
 *                             "srcSet"={
 *                                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                                 "medium"="медиатека · URL medium (до 1200px)",
 *                                 "original"="медиатека · URL оригинала"
 *                             }
 *                         }
 *                     },
 *                     "video"={
 *                         "src"="модель · URL видео",
 *                         "mime"="модель · MIME видео"
 *                     },
 *                     "fittingRoomUrl"="модель · ссылка на 3D-примерочную",
 *                     "dimensions"={
 *                         "overallSize"="модель · габарит Ш×В×Г, мм",
 *                         "seatDepth"="модель · глубина сиденья, мм",
 *                         "seatHeight"="модель · высота сиденья, мм",
 *                         "armrestWidth"="модель · ширина подлокотника, мм",
 *                         "legHeight"="модель · высота опоры, мм",
 *                         "unit"="модель · единица (всегда mm)"
 *                     },
 *                     "materials"={
 *                         "frameSpec"="модель · каркас, тех. описание",
 *                         "mechanism"="модель · механизм",
 *                         "fillingSpec"="модель · наполнение, тех. описание",
 *                         "additional"="модель · дополнительно",
 *                         "frame"="модель · каркас для карточки",
 *                         "foundation"="модель · основание для карточки",
 *                         "filling"="модель · наполнение для карточки",
 *                         "upholstery"="модель · обивка для карточки",
 *                         "supports"="модель · опоры для карточки"
 *                     },
 *                     "modelImages"={
 *                         {
 *                             "src"="модель · ракурс, URL medium из медиатеки",
 *                             "alt"="медиатека · альтернативный текст",
 *                             "srcSet"={
 *                                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                                 "medium"="медиатека · URL medium (до 1200px)",
 *                                 "original"="медиатека · URL оригинала"
 *                             }
 *                         }
 *                     },
 *                     "modelInteriorImages"={
 *                         {
 *                             "src"="модель · фото в интерьере, URL medium из медиатеки",
 *                             "alt"="медиатека · альтернативный текст",
 *                             "srcSet"={
 *                                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                                 "medium"="медиатека · URL medium (до 1200px)",
 *                                 "original"="медиатека · URL оригинала"
 *                             }
 *                         }
 *                     },
 *                     "dimensionImages"={
 *                         {
 *                             "src"="модель · тех.фото габаритов, URL medium из медиатеки",
 *                             "alt"="медиатека · альтернативный текст",
 *                             "srcSet"={
 *                                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                                 "medium"="медиатека · URL medium (до 1200px)",
 *                                 "original"="медиатека · URL оригинала"
 *                             }
 *                         }
 *                     },
 *                     "techPhotosFolderUrl"="модель · ссылка на облачную папку с тех.фото (если файлы не в медиатеке)",
 *                     "prices"={
 *                         "1"="модель · цена 1-й категории ткани",
 *                         "2"="модель · цена 2-й категории ткани",
 *                         "25"="модель · цена 25-й категории ткани"
 *                     }
 *                 },
 *                 "modelImages"={
 *                     {
 *                         "src"="модель · ракурс, URL medium из медиатеки",
 *                         "alt"="медиатека · альтернативный текст",
 *                         "srcSet"={
 *                             "mini"="медиатека · URL миниатюры (до 200px)",
 *                             "medium"="медиатека · URL medium (до 1200px)",
 *                             "original"="медиатека · URL оригинала"
 *                         }
 *                     }
 *                 },
 *                 "modelInteriorImages"={
 *                     {
 *                         "src"="модель · фото в интерьере, URL medium из медиатеки",
 *                         "alt"="медиатека · альтернативный текст",
 *                         "srcSet"={
 *                             "mini"="медиатека · URL миниатюры (до 200px)",
 *                             "medium"="медиатека · URL medium (до 1200px)",
 *                             "original"="медиатека · URL оригинала"
 *                         }
 *                     }
 *                 },
 *                 "dimensionImages"={
 *                     {
 *                         "src"="модель · тех.фото габаритов, URL medium из медиатеки",
 *                         "alt"="медиатека · альтернативный текст",
 *                         "srcSet"={
 *                             "mini"="медиатека · URL миниатюры (до 200px)",
 *                             "medium"="медиатека · URL medium (до 1200px)",
 *                             "original"="медиатека · URL оригинала"
 *                         }
 *                     }
 *                 },
 *                 "fabricColor"={
 *                     "id"="ткань · slug цвета (design_code)",
 *                     "label"="GUCCI 422",
 *                     "category"=1,
 *                     "meterPrice"="ткань · цена погонного метра коллекции",
 *                     "hexColor"="справочник цветов · HEX",
 *                     "collection"="ткань · название коллекции ткани",
 *                     "swatches"={
 *                         {
 *                             "src"="ткань · образец, URL medium из медиатеки",
 *                             "alt"="медиатека · альтернативный текст",
 *                             "srcSet"={
 *                                 "mini"="медиатека · URL миниатюры (до 200px)",
 *                                 "medium"="медиатека · URL medium (до 1200px)",
 *                                 "original"="медиатека · URL оригинала"
 *                             }
 *                         }
 *                     }
 *                 }
 *             }
 *         }
 *     },
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         title="список товаров",
 *         description="список SKU подкатегории",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuProduct")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogBreadcrumbItem",
 *     type="object",
 *     required={"level","id","label"},
 *     @OA\Property(property="level", type="string", enum={"direction","collection","category","subcategory"}, description="уровень иерархии"),
 *     @OA\Property(property="id", type="string", description="slug узла"),
 *     @OA\Property(property="label", type="string", description="отображаемое название")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogNavigationNode",
 *     type="object",
 *     required={"id","label"},
 *     @OA\Property(property="id", type="string", description="slug узла"),
 *     @OA\Property(property="label", type="string", description="название"),
 *     @OA\Property(property="count", type="integer", nullable=true, description="число SKU в scope")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogNavigationResponse",
 *     type="object",
 *     required={"breadcrumb","level","items"},
 *     @OA\Property(property="breadcrumb", type="array", @OA\Items(ref="#/components/schemas/CatalogBreadcrumbItem")),
 *     @OA\Property(property="level", type="string", enum={"direction","collection","category","subcategory"}),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogNavigationNode")),
 *     @OA\Property(property="meta", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFilterRange",
 *     type="object",
 *     required={"min","max"},
 *     @OA\Property(property="min", type="integer"),
 *     @OA\Property(property="max", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFilterColorOption",
 *     type="object",
 *     required={"id","label","count"},
 *     @OA\Property(property="id", type="string", description="slug catalog_colors"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="hexColor", type="string", nullable=true),
 *     @OA\Property(property="count", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFilterTextureOption",
 *     type="object",
 *     required={"id","label","count"},
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="count", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFilterFunctionOption",
 *     type="object",
 *     required={"id","label","count"},
 *     @OA\Property(property="id", type="string", example="sleeping", description="sleeping — спальное место; foldable — раскладной"),
 *     @OA\Property(property="label", type="string", example="Спальное место"),
 *     @OA\Property(property="count", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductFilters",
 *     type="object",
 *     description="Facets для UI фильтров по текущему scope",
 *     @OA\Property(property="price", ref="#/components/schemas/CatalogFilterRange", nullable=true),
 *     @OA\Property(property="colors", type="array", @OA\Items(ref="#/components/schemas/CatalogFilterColorOption")),
 *     @OA\Property(property="textures", type="array", @OA\Items(ref="#/components/schemas/CatalogFilterTextureOption")),
 *     @OA\Property(property="functions", type="array", description="Функции (фильтры по признакам товара)", @OA\Items(ref="#/components/schemas/CatalogFilterFunctionOption")),
 *     @OA\Property(
 *         property="dimensions",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="width", ref="#/components/schemas/CatalogFilterRange", nullable=true),
 *         @OA\Property(property="height", ref="#/components/schemas/CatalogFilterRange", nullable=true),
 *         @OA\Property(property="depth", ref="#/components/schemas/CatalogFilterRange", nullable=true),
 *         @OA\Property(property="unit", type="string", example="mm")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogListingSortQuery",
 *     type="string",
 *     default="default",
 *     enum={"default","price_asc","price_desc","popular","new","alphabet"},
 *     description="Сортировка листинга каталога (query sort). default и popular — одинаковый round-robin по model_id (чередование линеек и цветов, внутри модели цена ↑); на page 1 при scope direction — приоритетные образцы SKU (listingPriorityOrder). popular не включает бейдж «Популярное» сам по себе — только badge/badges SKU и isPopular. alphabet — round-robin коллекции A→Z × цена ↑ без приоритетных SKU. price_asc | price_desc | new — round-robin по model_id с выбранным критерием; price_asc упорядочивает модели по минимальной цене SKU. layout=collectionGroups — пагинация по линейкам. В query дефисы нормализуются в underscore (price-asc → price_asc)."
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductsMeta",
 *     type="object",
 *     required={"total","page","perPage","sort","scopeMode","layout","applied"},
 *     @OA\Property(property="total", type="integer", description="layout=flat — число SKU; layout=collectionGroups — число линеек моделей (catalog_collections) с товарами после фильтров"),
 *     @OA\Property(property="page", type="integer", description="Текущая страница из query page (default 1)"),
 *     @OA\Property(property="perPage", type="integer", description="layout=flat — SKU на странице (default 24, max 100); layout=collectionGroups — линеек на странице"),
 *     @OA\Property(property="sort", ref="#/components/schemas/CatalogListingSortQuery", description="Применённое значение sort из query"),
 *     @OA\Property(property="scopeMode", type="string", enum={"all","shortcut","chain"}, description="all — GET /catalog/products без scope; shortcut | chain — фильтр по разделу"),
 *     @OA\Property(property="layout", type="string", enum={"flat","collectionGroups"}, description="flat — items[] плоский список SKU; collectionGroups — items[] секции линеек"),
 *     @OA\Property(property="itemsPerGroup", type="integer", example=3, description="Только при layout=collectionGroups: SKU на линейку (query itemsPerGroup, default 3, max 12)"),
 *     @OA\Property(property="applied", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogModelLineGroup",
 *     type="object",
 *     description="Секция линейки моделей (catalog_collections) при collection=true. Элемент массива items[] при meta.layout=collectionGroups.",
 *     required={"slug","label","title","href","total","items"},
 *     @OA\Property(property="slug", type="string", example="artemida", description="slug линейки (catalog_collections)"),
 *     @OA\Property(property="label", type="string", example="Коллекция"),
 *     @OA\Property(property="title", type="string", example="Артемида"),
 *     @OA\Property(property="directionSlug", type="string", nullable=true, example="a-plus", description="slug направления"),
 *     @OA\Property(property="href", type="string", example="/catalog/artemida"),
 *     @OA\Property(property="total", type="integer", example=42, description="Число SKU линейки после scope и фильтров"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogProductCard"))
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductsResponse",
 *     type="object",
 *     deprecated=true,
 *     description="Legacy alias. Используйте GET /api/v1/catalog/products или GET /api/v1/catalog/menu с scope.",
 *     required={"breadcrumb","items","filters","meta"},
 *     @OA\Property(property="breadcrumb", type="array", @OA\Items(ref="#/components/schemas/CatalogBreadcrumbItem")),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogMenuProduct")),
 *     @OA\Property(property="filters", ref="#/components/schemas/CatalogProductFilters"),
 *     @OA\Property(property="meta", ref="#/components/schemas/CatalogProductsMeta")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogSearchProduct",
 *     type="object",
 *     description="Товар в autocomplete и превью «Найдено в категориях»: одно фото (SKU, иначе первое фото модели), название, подкатегория, коллекция, цены и бейдж.",
 *     required={"id","slug","title","image"},
 *     @OA\Property(property="id", type="string", description="slug товара (SKU)"),
 *     @OA\Property(property="slug", type="string", description="slug товара (SKU)"),
 *     @OA\Property(property="title", type="string", description="Название товара"),
 *     @OA\Property(property="subcategory", type="string", nullable=true, description="Название подкатегории"),
 *     @OA\Property(property="collection", type="string", nullable=true, description="Название коллекции мебели"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", description="Фото SKU; если нет — первое фото модели"),
 *     @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога"),
 *     @OA\Property(property="priceDisplay", type="string", nullable=true, description="Отформатированная розничная цена"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции (Bearer дилера)"),
 *     @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="Персональная скидка дилера или % акции"),
 *     @OA\Property(property="badge", ref="#/components/schemas/CatalogBadge", nullable=true),
 *     @OA\Property(property="href", type="string", nullable=true, description="Ссылка на карточку товара")
 * )
 *
 * @OA\Schema(
 *     schema="PageSeo",
 *     type="object",
 *     @OA\Property(property="title", type="string", example="Журнал — МФ Анна"),
 *     @OA\Property(property="description", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageHeroMedia",
 *     type="object",
 *     description="Баннер страницы (единая схема с главной)",
 *     @OA\Property(property="collectionLabel", type="string", nullable=true, example="Коллекция"),
 *     @OA\Property(property="collectionTitle", type="string", nullable=true, example="А+"),
 *     @OA\Property(property="tagline", type="string", nullable=true),
 *     @OA\Property(property="year", type="string", nullable=true, example="2026"),
 *     @OA\Property(property="imageDesktop", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="imageMobile", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="JournalCardItem",
 *     type="object",
 *     description="Карточка статьи в списке вкладки журнала",
 *     required={"id","slug","title","to"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="slug", type="string", example="geometriya-komforta"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="date", type="string", example="07.10.2025"),
 *     @OA\Property(property="excerpt", type="string", nullable=true),
 *     @OA\Property(property="to", type="string", example="/journal/geometriya-komforta"),
 *     @OA\Property(property="imagePosition", type="string", nullable=true, example="center center"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="number", type="string", nullable=true, description="Номер на главной (01, 02…)", example="01")
 * )
 *
 * @OA\Schema(
 *     schema="JournalCategory",
 *     type="object",
 *     required={"id","label","items"},
 *     @OA\Property(property="id", type="string", example="process"),
 *     @OA\Property(property="label", type="string", example="Архитектура процессов"),
 *     @OA\Property(property="icon", type="string", nullable=true, example="journal-process"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/JournalCardItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageJournalResponse",
 *     type="object",
 *     description="Страница журнала: hero + вкладки; items подтягиваются из journal_articles",
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(property="hero", ref="#/components/schemas/PageHeroMedia", nullable=true),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/JournalCategory")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageFaqIntro",
 *     type="object",
 *     description="Вступление под баннером",
 *     @OA\Property(property="lead", type="string", example="FAQs"),
 *     @OA\Property(property="text", type="string", example="Мы собрали для вас ответы на самые часто задаваемые вопросы")
 * )
 *
 * @OA\Schema(
 *     schema="PageFaqTextPart",
 *     type="object",
 *     description="Фрагмент абзаца ответа. При s=link рендерить to как href ссылки",
 *     required={"t"},
 *     @OA\Property(property="t", type="string", description="Текст фрагмента", example="Контакты"),
 *     @OA\Property(property="to", type="string", nullable=true, description="URL ссылки", example="/contacts"),
 *     @OA\Property(
 *         property="s",
 *         type="string",
 *         nullable=true,
 *         enum={"link","brand"},
 *         description="link — гиперссылка; brand — выделение брендом",
 *         example="link"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageFaqItem",
 *     type="object",
 *     required={"question","paragraphs"},
 *     @OA\Property(property="question", type="string", example="Как выбрать подходящую модель?"),
 *     @OA\Property(
 *         property="paragraphs",
 *         type="array",
 *         description="Абзацы ответа: string (простой текст) или массив PageFaqTextPart (текст со ссылками)",
 *         @OA\Items(
 *             oneOf={
 *                 @OA\Schema(type="string", example="Простой абзац без ссылок."),
 *                 @OA\Schema(
 *                     type="array",
 *                     @OA\Items(ref="#/components/schemas/PageFaqTextPart")
 *                 )
 *             }
 *         ),
 *         example={
 *             "Найти идеальную модель легко прямо на сайте…",
 *             {
 *                 {"t":"…на странице «"},
 *                 {"t":"Контакты","to":"/contacts","s":"link"},
 *                 {"t":"»."}
 *             }
 *         }
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageFaqCategory",
 *     type="object",
 *     required={"id","label","items"},
 *     @OA\Property(property="id", type="string", example="order"),
 *     @OA\Property(property="label", type="string", example="Заказ и подбор"),
 *     @OA\Property(property="icon", type="string", nullable=true, example="faq-order"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PageFaqItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageFaqResponse",
 *     type="object",
 *     description="Страница FAQ: баннер, вступление и вкладки с вопросами",
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(property="hero", ref="#/components/schemas/PageHeroMedia", nullable=true),
 *     @OA\Property(property="intro", ref="#/components/schemas/PageFaqIntro", nullable=true),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PageFaqCategory")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="JournalArticleBlock",
 *     type="object",
 *     description="Блок контента статьи (в CMS собирается из Markdown редактора)",
 *     required={"type"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"text","heading","image","quote","divider","gallery"}
 *     ),
 *     @OA\Property(property="text", type="string", nullable=true, description="Простой текст блока (если без ссылок и один абзац)"),
 *     @OA\Property(
 *         property="paragraphs",
 *         type="array",
 *         nullable=true,
 *         description="Абзацы для text/quote: string или массив PageFaqTextPart (текст со ссылками), по аналогии с FAQ",
 *         @OA\Items(
 *             oneOf={
 *                 @OA\Schema(type="string"),
 *                 @OA\Schema(
 *                     type="array",
 *                     @OA\Items(ref="#/components/schemas/PageFaqTextPart")
 *                 )
 *             }
 *         )
 *     ),
 *     @OA\Property(property="level", type="integer", nullable=true, example=2),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true, description="В CMS src — ID медиа или ID#medium|large|original; в API — CatalogImage с выбранным вариантом"),
 *     @OA\Property(property="caption", type="string", nullable=true),
 *     @OA\Property(property="author", type="string", nullable=true),
 *     @OA\Property(property="columns", type="integer", nullable=true, example=2),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="JournalArticleResponse",
 *     type="object",
 *     description="Полная статья журнала",
 *     required={"slug","title","blocks"},
 *     @OA\Property(property="slug", type="string", example="geometriya-komforta"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="date", type="string", nullable=true, example="07.10.2025"),
 *     @OA\Property(property="readingTime", type="string", nullable=true, example="5 мин"),
 *     @OA\Property(property="categoryId", type="string", nullable=true, example="process"),
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(
 *         property="blocks",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/JournalArticleBlock")
 *     ),
 *     @OA\Property(
 *         property="recommendedProducts",
 *         type="array",
 *         description="Рекомендуемые товары статьи (как items в поиске / products в autocomplete)",
 *         @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="LegalDocumentPage",
 *     type="object",
 *     description="Текстовый юридический документ из CMS",
 *     required={"slug","title","blocks"},
 *     @OA\Property(property="slug", type="string", example="privacy-policy"),
 *     @OA\Property(property="title", type="string", example="Политика конфиденциальности"),
 *     @OA\Property(
 *         property="blocks",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/JournalArticleBlock")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="LegalDocumentsResponse",
 *     type="object",
 *     required={"privacyPolicy","userAgreement"},
 *     @OA\Property(property="privacyPolicy", ref="#/components/schemas/LegalDocumentPage", nullable=true),
 *     @OA\Property(property="userAgreement", ref="#/components/schemas/LegalDocumentPage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutHero",
 *     type="object",
 *     description="Баннер страницы «О нас»",
 *     @OA\Property(property="label", type="string", example="О нас"),
 *     @OA\Property(property="brandTitle", type="string", example="МФ Анна"),
 *     @OA\Property(property="tagline", type="string", nullable=true, example="Мастерство создания эстетики жизни"),
 *     @OA\Property(property="imageDesktop", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="imageMobile", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageIntroBlock",
 *     type="object",
 *     description="Текст с абзацами, изображением и опциональной галереей",
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(
 *         property="paragraphs",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(
 *         property="slides",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(type="object", @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutCommunity",
 *     type="object",
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(
 *         property="cards",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutTimelineStage",
 *     type="object",
 *     @OA\Property(property="year", type="string", example="1995"),
 *     @OA\Property(property="label", type="string", example="1995"),
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="text", type="string", nullable=true),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true, description="Первое фото этапа (legacy)"),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutTimeline",
 *     type="object",
 *     @OA\Property(
 *         property="stages",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PageAboutTimelineStage")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageTitleTextBlock",
 *     type="object",
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="text", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutJob",
 *     type="object",
 *     description="Тизер вакансии на странице «О нас»",
 *     required={"category","title","slug"},
 *     @OA\Property(property="category", type="string", example="Производство и разработка"),
 *     @OA\Property(property="title", type="string", example="Мастер по работе с деревом"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="salary", type="string", nullable=true, example="от 100 000 ₽"),
 *     @OA\Property(property="slug", type="string", example="master-po-rabote-s-derevom")
 * )
 *
 * @OA\Schema(
 *     schema="PageAboutResponse",
 *     type="object",
 *     description="Страница «О нас»: блоки контента и до 4 вакансий из разных направлений",
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(property="hero", ref="#/components/schemas/PageAboutHero", nullable=true),
 *     @OA\Property(property="intro", ref="#/components/schemas/PageIntroBlock", nullable=true),
 *     @OA\Property(property="community", ref="#/components/schemas/PageAboutCommunity", nullable=true),
 *     @OA\Property(property="timeline", ref="#/components/schemas/PageAboutTimeline", nullable=true),
 *     @OA\Property(property="jobsIntro", ref="#/components/schemas/PageTitleTextBlock", nullable=true),
 *     @OA\Property(
 *         property="jobs",
 *         type="array",
 *         description="До 4 активных вакансий. Сначала с show_on_about; round-robin по направлениям (vacancy_directions); если меньше 4 — все доступные.",
 *         maxItems=4,
 *         @OA\Items(ref="#/components/schemas/PageAboutJob")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageVacanciesHero",
 *     type="object",
 *     description="Баннер страницы «Вакансии»",
 *     @OA\Property(property="label", type="string", example="Вакансии"),
 *     @OA\Property(property="title", type="string", example="Культура труда и созидания"),
 *     @OA\Property(property="tagline", type="string", nullable=true),
 *     @OA\Property(property="imageDesktop", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="imageMobile", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageGallerySlides",
 *     type="object",
 *     @OA\Property(
 *         property="slides",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="VacancyListingJob",
 *     type="object",
 *     description="Вакансия в листинге группы",
 *     required={"slug","title"},
 *     @OA\Property(property="slug", type="string", example="master-po-rabote-s-derevom"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="salary", type="string", nullable=true),
 *     @OA\Property(property="salaryMobile", type="string", nullable=true),
 *     @OA\Property(property="meta", type="string", nullable=true, description="Отдел · занятость · место (одной строкой)"),
 *     @OA\Property(property="department", type="string", nullable=true),
 *     @OA\Property(property="schedule", type="string", nullable=true, example="Полный день"),
 *     @OA\Property(property="location", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VacancyGroup",
 *     type="object",
 *     description="Направление вакансий (таблица vacancy_directions). Список и порядок задаются в админке; id — slug направления.",
 *     required={"id","jobs"},
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         example="production",
 *         description="slug направления (API-ключ группы)"
 *     ),
 *     @OA\Property(property="number", type="string", example="01"),
 *     @OA\Property(property="title", type="string", example="Производство и разработка"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="emptyTitle", type="string", nullable=true),
 *     @OA\Property(property="emptyDescription", type="string", nullable=true),
 *     @OA\Property(
 *         property="jobs",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/VacancyListingJob")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageVacanciesResponse",
 *     type="object",
 *     description="Страница «Вакансии»: hero, values (вступление с title/paragraphs/image/slides), gallery, groups[] — направления с jobs[] из БД (не из content_blocks).",
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(property="hero", ref="#/components/schemas/PageVacanciesHero", nullable=true),
 *     @OA\Property(property="gallery", ref="#/components/schemas/PageGallerySlides", nullable=true),
 *     @OA\Property(property="values", ref="#/components/schemas/PageIntroBlock", nullable=true, description="Блок «Вступление»: заголовок, абзацы, главное фото и галерея slides[]"),
 *     @OA\Property(
 *         property="groups",
 *         type="array",
 *         description="Активные направления vacancy_directions с активными вакансиями",
 *         @OA\Items(ref="#/components/schemas/VacancyGroup")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="VacancyDetailResponse",
 *     type="object",
 *     description="Детальная карточка вакансии (GET /api/v1/pages/vacancies/{slug})",
 *     required={"slug","title"},
 *     @OA\Property(property="slug", type="string", example="master-po-rabote-s-derevom"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="salary", type="string", nullable=true),
 *     @OA\Property(property="department", type="string", nullable=true),
 *     @OA\Property(property="schedule", type="string", nullable=true),
 *     @OA\Property(property="location", type="string", nullable=true),
 *     @OA\Property(property="directionId", type="string", nullable=true, description="slug направления"),
 *     @OA\Property(property="directionTitle", type="string", nullable=true, description="название направления"),
 *     @OA\Property(property="postedAt", type="string", nullable=true, format="date", example="2025-10-01"),
 *     @OA\Property(
 *         property="requirements",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="conditions",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageContactsInfo",
 *     type="object",
 *     description="Контактные данные на странице «Контакты» (блок info)",
 *     @OA\Property(property="phone", type="string", example="+7 (989) 423-20-00"),
 *     @OA\Property(property="phoneHref", type="string", example="tel:+79894232000"),
 *     @OA\Property(property="email", type="string", example="mebelanna@internet.ru"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="telegram", type="string", nullable=true, description="Ссылка на Telegram"),
 *     @OA\Property(property="vkontakte", type="string", nullable=true, description="Ссылка на ВКонтакте"),
 *     @OA\Property(property="max", type="string", nullable=true, description="Ссылка на мессенджер MAX")
 * )
 *
 * @OA\Schema(
 *     schema="PageContactCta",
 *     type="object",
 *     description="Блок формы на странице «Контакты» (contact)",
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="subtitle", type="string"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="privacyPolicyUrl", type="string", nullable=true, description="PDF политики конфиденциальности для чекбокса формы"),
 *     @OA\Property(property="userAgreementUrl", type="string", nullable=true, description="PDF пользовательского соглашения для чекбокса формы")
 * )
 *
 * @OA\Schema(
 *     schema="PageContentResponse",
 *     type="object",
 *     description="Контент страницы: ключи — block_key (hero, seo, intro, categories, contact, …), значения — JSON блока.",
 *     @OA\AdditionalProperties(type="object")
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeHero",
 *     type="object",
 *     description="Главный баннер",
 *     @OA\Property(property="collectionLabel", type="string", nullable=true, example="Коллекция"),
 *     @OA\Property(property="collectionTitle", type="string", nullable=true, example="А+"),
 *     @OA\Property(property="tagline", type="string", nullable=true),
 *     @OA\Property(property="year", type="string", nullable=true, example="2026"),
 *     @OA\Property(property="imageDesktop", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="imageMobile", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageHomePhilosophy",
 *     type="object",
 *     description="Философия бренда (блок под баннером, редактируется вместе с коллекциями на главной)",
 *     @OA\Property(property="text", type="string", example="Мы создаем мебельные решения…")
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeCollectionSlide",
 *     type="object",
 *     required={"label","href","image"},
 *     @OA\Property(property="label", type="string", example="Диваны"),
 *     @OA\Property(property="href", type="string", example="/catalog/a-plus/divany"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage"),
 *     @OA\Property(property="objectPosition", type="string", nullable=true, example="center 30%")
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeCollectionCard",
 *     type="object",
 *     required={"id","title","slides"},
 *     @OA\Property(property="id", type="string", description="slug коллекции каталога", example="a-plus"),
 *     @OA\Property(property="title", type="string", example="А+"),
 *     @OA\Property(property="titleUppercase", type="boolean", nullable=true, example=true),
 *     @OA\Property(
 *         property="slides",
 *         type="array",
 *         description="До 3 фото с заголовком на карточку",
 *         @OA\Items(ref="#/components/schemas/PageHomeCollectionSlide")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeProductCard",
 *     type="object",
 *     @OA\Property(property="number", type="string", example="01"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="collection", type="string", nullable=true),
 *     @OA\Property(property="fabric", type="string", nullable=true),
 *     @OA\Property(property="price", type="string", nullable=true),
 *     @OA\Property(property="swatchCount", type="string", nullable=true, example="+8"),
 *     @OA\Property(property="layout", type="string", nullable=true, example="featured"),
 *     @OA\Property(property="imagePosition", type="string", nullable=true),
 *     @OA\Property(property="to", type="string", nullable=true),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PageHomePartners",
 *     type="object",
 *     @OA\Property(property="intro", type="string", nullable=true),
 *     @OA\Property(
 *         property="cards",
 *         type="array",
 *         @OA\Items(type="object")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeJournalSection",
 *     type="object",
 *     description="Блок последних статей на главной",
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="to", type="string", nullable=true, example="/journal"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/JournalCardItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PageHomeResponse",
 *     type="object",
 *     description="Главная страница",
 *     @OA\Property(property="seo", ref="#/components/schemas/PageSeo", nullable=true),
 *     @OA\Property(property="hero", ref="#/components/schemas/PageHomeHero", nullable=true),
 *     @OA\Property(property="philosophy", ref="#/components/schemas/PageHomePhilosophy", nullable=true),
 *     @OA\Property(
 *         property="collections",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PageHomeCollectionCard")
 *     ),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PageHomeProductCard")
 *     ),
 *     @OA\Property(property="partners", ref="#/components/schemas/PageHomePartners", nullable=true),
 *     @OA\Property(property="journal", ref="#/components/schemas/PageHomeJournalSection", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuSubcategory",
 *     type="object",
 *     required={"id","label"},
 *     @OA\Property(property="id", type="string", example="straight"),
 *     @OA\Property(property="label", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuDirection",
 *     type="object",
 *     description="Направление каталога (Диваны, Кресла…)",
 *     required={"id","label","subcategories"},
 *     @OA\Property(property="id", type="string", example="sofas"),
 *     @OA\Property(property="label", type="string", example="Диваны"),
 *     @OA\Property(
 *         property="subcategories",
 *         type="array",
 *         description="Уникальные подкатегории по всем коллекциям направления",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuSubcategory")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuGroup",
 *     type="object",
 *     description="Legacy-алиас для directions (то же содержимое)",
 *     required={"id","label","subcategories"},
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(
 *         property="subcategories",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuSubcategory")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuCollection",
 *     type="object",
 *     required={"id","title","href"},
 *     @OA\Property(property="id", type="string", description="slug коллекции"),
 *     @OA\Property(property="groupId", type="string", nullable=true, description="legacy: slug направления"),
 *     @OA\Property(property="directionId", type="string", nullable=true, description="slug направления"),
 *     @OA\Property(property="label", type="string", nullable=true),
 *     @OA\Property(property="name", type="string", nullable=true, description="отображаемое имя"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="titleUppercase", type="boolean", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="href", type="string"),
 *     @OA\Property(property="ctaLabel", type="string", nullable=true),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(ref="#/components/schemas/CatalogImage")
 *     ),
 *     @OA\Property(property="imagePosition", type="string", nullable=true, example="center")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogMenuResponse",
 *     type="object",
 *     required={"directions","groups","collections","modelLines"},
 *     @OA\Property(
 *         property="directions",
 *         type="array",
 *         description="Legacy: направления (@deprecated — collections)",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuDirection")
 *     ),
 *     @OA\Property(
 *         property="groups",
 *         type="array",
 *         description="Legacy-алиас directions",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuGroup")
 *     ),
 *     @OA\Property(
 *         property="collections",
 *         type="array",
 *         description="Коллекции UI (= direction)",
 *         @OA\Items(ref="#/components/schemas/CatalogFrontendCollection")
 *     ),
 *     @OA\Property(
 *         property="modelLines",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CatalogModelLine")
 *     ),
 *     @OA\Property(
 *         property="menuCollections",
 *         type="array",
 *         description="Legacy полный payload линеек",
 *         @OA\Items(ref="#/components/schemas/CatalogMenuCollection")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogFrontendCollection",
 *     type="object",
 *     required={"slug","label","title"},
 *     @OA\Property(property="slug", type="string", example="a-plus"),
 *     @OA\Property(property="label", type="string", example="А+"),
 *     @OA\Property(property="title", type="string", example="А+")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogModelLine",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CatalogMenuCollection"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="directionSlug", type="string", example="a-plus"),
 *             @OA\Property(property="modelLineSlug", type="string", example="artemida")
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="CatalogTaxonomyCategory",
 *     type="object",
 *     required={"slug","label","subcategories"},
 *     @OA\Property(property="slug", type="string", example="divany"),
 *     @OA\Property(property="label", type="string", example="Диваны"),
 *     @OA\Property(property="subcategories", type="array", @OA\Items(ref="#/components/schemas/CatalogTaxonomySubcategory"))
 * )
 *
 * @OA\Schema(
 *     schema="CatalogTaxonomySubcategory",
 *     type="object",
 *     required={"slug","label","count"},
 *     @OA\Property(property="slug", type="string", example="pryamye"),
 *     @OA\Property(property="label", type="string", example="Прямые"),
 *     @OA\Property(property="count", type="integer", example=12)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductSwatch",
 *     type="object",
 *     description="Оттенок модели для превью в листинге: hexColor для кружка 16px; src — опциональное фото образца (mini).",
 *     required={"hexColor","alt"},
 *     @OA\Property(property="hexColor", type="string", example="#c9b8a0", description="HEX из справочника catalog_colors"),
 *     @OA\Property(property="src", type="string", nullable=true, example="медиатека · URL mini образца", description="Необязательно: mini-URL swatchMedia ткани или справочника"),
 *     @OA\Property(property="alt", type="string", example="Бежевый", description="Русское название оттенка из catalog_colors.label")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryTaxonomyRef",
 *     type="object",
 *     required={"slug","label"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="label", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryCollectionRef",
 *     type="object",
 *     required={"slug","label","title"},
 *     @OA\Property(property="slug", type="string", example="artemida"),
 *     @OA\Property(property="label", type="string", example="Коллекция"),
 *     @OA\Property(property="title", type="string", example="Артемида")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryProductItem",
 *     type="object",
 *     description="Сокращённая карточка для GET /api/v1/catalog/library-products (одна SKU на коллекцию мебели).",
 *     required={"image"},
 *     @OA\Property(property="direction", ref="#/components/schemas/CatalogLibraryTaxonomyRef", nullable=true),
 *     @OA\Property(property="category", ref="#/components/schemas/CatalogLibraryTaxonomyRef", nullable=true),
 *     @OA\Property(property="subcategory", ref="#/components/schemas/CatalogLibraryTaxonomyRef", nullable=true),
 *     @OA\Property(property="collection", ref="#/components/schemas/CatalogLibraryCollectionRef", nullable=true),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", description="Первое фото SKU"),
 *     @OA\Property(property="polygons_3d", type="string", nullable=true, description="Количество полигонов модели"),
 *     @OA\Property(property="file_3d_url", type="string", nullable=true, description="Публичный URL сохранённого файла 3D из медиатеки (file_3d_id), не внешняя ссылка"),
 *     @OA\Property(property="badge", ref="#/components/schemas/CatalogBadge", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryProductsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogLibraryProductItem")),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="meta.total — число коллекций; meta.perPage — коллекций на странице",
 *         allOf={@OA\Schema(ref="#/components/schemas/CatalogProductsMeta")}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryFabricItem",
 *     type="object",
 *     required={"fabricColorId","slug","label","isRecommendedFabric"},
 *     @OA\Property(property="fabricColorId", type="integer", example=42),
 *     @OA\Property(property="slug", type="string", description="slug catalog_colors или design_code"),
 *     @OA\Property(property="label", type="string", example="GUCCI 422"),
 *     @OA\Property(property="collection", type="string", nullable=true, example="GUCCI"),
 *     @OA\Property(property="designCode", type="string", example="422"),
 *     @OA\Property(property="colorId", type="string", nullable=true),
 *     @OA\Property(property="colorName", type="string", nullable=true, example="Терракота"),
 *     @OA\Property(property="hexColor", type="string", nullable=true),
 *     @OA\Property(property="texture", type="string", nullable=true, example="Букле"),
 *     @OA\Property(property="composition", type="string", nullable=true, description="Состав коллекции ткани"),
 *     @OA\Property(property="martindale", type="integer", nullable=true, description="Износостойкость, циклы Мартиндейла (из коллекции)"),
 *     @OA\Property(property="isRecommendedFabric", type="boolean"),
 *     @OA\Property(property="positionNumber", type="integer", nullable=true, description="Порядок среди рекомендуемых"),
 *     @OA\Property(property="description", type="string", nullable=true, description="Описание цветодизайна"),
 *     @OA\Property(property="swatch", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CatalogLibraryFabricsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogLibraryFabricItem")),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         required={"total","page","perPage"},
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="page", type="integer"),
 *         @OA\Property(property="perPage", type="integer"),
 *         @OA\Property(
 *             property="texturesArchiveUrl",
 *             type="string",
 *             nullable=true,
 *             example="https://dev.back-p-833.tw1.ru/files/library-fabrics.zip",
 *             description="Абсолютный URL готового ZIP с фото образцов (структура: фактура/коллекция/design_code.ext). Поле есть только если файл archives собран на сервере; пересборка — админка «Ткани», импорт реестра, изменение фото."
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductCard",
 *     type="object",
 *     required={"slug","id","name","href","image","swatches","swatchCount","images"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="fabric", type="string", nullable=true, example="GUCCI 422", description="коллекция ткани и design-code через пробел"),
 *     @OA\Property(property="isPopular", type="boolean", description="Флаг is_popular у SKU (не путать с sort=popular и не выводить бейдж без badge/badges)"),
 *     @OA\Property(property="listingPriorityOrder", type="integer", nullable=true, minimum=1, description="№ в списке приоритетов каталога (/admin/search?tab=catalog) на page 1 при scope direction; только у настроенных образцов SKU"),
 *     @OA\Property(property="badge", ref="#/components/schemas/CatalogBadge", nullable=true, description="Первый элемент badges"),
 *     @OA\Property(property="badges", type="array", nullable=true, @OA\Items(ref="#/components/schemas/CatalogBadge")),
 *     @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога (price_amount)"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции (только Bearer дилера)"),
 *     @OA\Property(property="priceDisplay", type="string", nullable=true, description="Отформатированная розничная цена каталога для UI"),
 *     @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="effectiveDiscountPercent; omit при catalogPromotion"),
 *     @OA\Property(property="catalogPromotion", ref="#/components/schemas/CatalogPromotionActive", nullable=true),
 *     @OA\Property(property="href", type="string", example="/product/turin-grey"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage"),
 *     @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/CatalogImage")),
 *     @OA\Property(property="swatches", type="array", description="До 3 оттенков модели; при ≤3 цветах ткани — все без dedupe, иначе уникальные по catalog_colors", @OA\Items(ref="#/components/schemas/CatalogProductSwatch")),
 *     @OA\Property(property="swatchCount", type="integer", description="Число цветов модели: все fabric-цвета при ≤3, иначе уникальные оттенки catalog_colors")
 * )
 *
 * @OA\Schema(
 *     schema="CatalogProductDetailResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CatalogMenuProduct"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="slug", type="string"),
 *             @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога (price_amount)"),
 *             @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции (только Bearer дилера)"),
 *             @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="effectiveDiscountPercent; omit при catalogPromotion"),
 *             @OA\Property(property="catalogPromotion", ref="#/components/schemas/CatalogPromotionActive", nullable=true),
 *             @OA\Property(property="badges", type="array", nullable=true, @OA\Items(ref="#/components/schemas/CatalogBadge")),
 *             @OA\Property(property="modelLineSlug", type="string", nullable=true, example="artemida", description="slug линейки моделей (коллекции)"),
 *             @OA\Property(property="collectionSlug", type="string", nullable=true),
 *             @OA\Property(property="categorySlug", type="string", nullable=true),
 *             @OA\Property(property="subcategorySlug", type="string", nullable=true),
 *             @OA\Property(
 *                 property="fabrics",
 *                 type="array",
 *                 description="Ткани модели: фактура → коллекция → цвета (только для SKU, сгенерированных из модели)",
 *                 @OA\Items(ref="#/components/schemas/CatalogFabricVariantGroup")
 *             ),
 *             @OA\Property(property="recommended", type="array", description="До 8 SKU той же подкатегории, sort=default (round-robin по model_id), без текущего товара и кастом-SKU", @OA\Items(ref="#/components/schemas/CatalogProductCard")),
 *             @OA\Property(
 *                 property="orderFormBlank",
 *                 ref="#/components/schemas/DealerPriceListFile",
 *                 nullable=true,
 *                 description="Актуальный бланк заказа (загрузка в админке /admin/user/index, вкладка «Дилеры»); omit, если бланк не загружен"
 *             )
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="CatalogUnifiedResponse",
 *     type="object",
 *     description="Единый ответ GET /api/v1/catalog/menu и GET /api/v1/catalog/products: меню + навигация; items/filters/meta — при scope, collection=true или на /products. meta.layout=flat — items[] = SKU; meta.layout=collectionGroups — items[] = CatalogModelLineGroup[] (collection=true).",
 *     required={"directions","groups","collections","breadcrumb","level","navigationItems","items","filters"},
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CatalogMenuResponse"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="breadcrumb", type="array", @OA\Items(ref="#/components/schemas/CatalogBreadcrumbItem")),
 *             @OA\Property(property="level", type="string", enum={"direction","collection","category","subcategory"}),
 *             @OA\Property(property="navigationItems", type="array", @OA\Items(ref="#/components/schemas/CatalogNavigationNode")),
 *             @OA\Property(property="navigationMeta", type="object", nullable=true),
 *             @OA\Property(
 *                 property="items",
 *                 type="array",
 *                 description="meta.layout=flat — CatalogProductCard[]; meta.layout=collectionGroups — CatalogModelLineGroup[]",
 *                 @OA\Items(oneOf={
 *                     @OA\Schema(ref="#/components/schemas/CatalogProductCard"),
 *                     @OA\Schema(ref="#/components/schemas/CatalogModelLineGroup")
 *                 })
 *             ),
 *             @OA\Property(property="categories", type="array", @OA\Items(ref="#/components/schemas/CatalogTaxonomyCategory")),
 *             @OA\Property(property="filters", ref="#/components/schemas/CatalogProductFilters"),
 *             @OA\Property(property="meta", ref="#/components/schemas/CatalogProductsMeta", nullable=true)
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="DaDataSuggestRequest",
 *     required={"query"},
 *     @OA\Property(property="query", type="string", example="Краснодар", description="Название города или полный адрес в одной строке"),
 *     @OA\Property(property="count", type="integer", default=10, description="Количество подсказок (1–20)")
 * )
 *
 * @OA\Schema(
 *     schema="DaDataAddressSuggestionData",
 *     @OA\Property(property="city_fias_id", type="string", nullable=true, description="ФИАС ID города"),
 *     @OA\Property(property="address_fias_id", type="string", nullable=true),
 *     @OA\Property(property="house_fias_id", type="string", nullable=true),
 *     @OA\Property(property="geo_lat", type="string", nullable=true),
 *     @OA\Property(property="geo_lon", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DaDataAddressSuggestion",
 *     description="Подсказка полного адреса",
 *     @OA\Property(property="value", type="string", example="г Москва, ул Тверская, д 7", description="Краткая подпись для списка подсказок"),
 *     @OA\Property(property="full_address", type="string", example="125009, г Москва, Тверской р-н, ул Тверская, д 7", description="Полный адрес с почтовым индексом"),
 *     @OA\Property(property="postal_code", type="string", nullable=true, example="125009"),
 *     @OA\Property(property="city_name", type="string", nullable=true, example="г Москва"),
 *     @OA\Property(property="data", ref="#/components/schemas/DaDataAddressSuggestionData")
 * )
 *
 * @OA\Schema(
 *     schema="DaDataSuggestResponse",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DaDataAddressSuggestion")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SearchCategoryItem",
 *     type="object",
 *     description="Категория для пустого состояния поиска (bootstrap). Настраивается в админке.",
 *     required={"id","label","icon","href"},
 *     @OA\Property(property="id", type="string", example="sofas"),
 *     @OA\Property(property="label", type="string", example="Диваны"),
 *     @OA\Property(property="icon", type="string", example="search-sofa", description="Иконка для UI"),
 *     @OA\Property(property="href", type="string", example="/catalog/a-plus/divany")
 * )
 *
 * @OA\Schema(
 *     schema="SearchBootstrapResponse",
 *     type="object",
 *     description="Пустое состояние строки поиска: частые запросы, быстрые категории и рекомендованные товары. Вызывается до ввода запроса или при фокусе на пустое поле.",
 *     @OA\Property(
 *         property="frequent",
 *         type="array",
 *         description="Частые запросы из админки (`search_frequent_queries`)",
 *         @OA\Items(type="string", example="прямой диван")
 *     ),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         description="Быстрые категории из админки (`search_categories`)",
 *         @OA\Items(ref="#/components/schemas/SearchCategoryItem")
 *     ),
 *     @OA\Property(
 *         property="recommended",
 *         type="array",
 *         description="Все рекомендованные SKU для пустого состояния: main + directions + categories + subcategories",
 *         @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *     ),
 *     @OA\Property(
 *         property="recommendedGroups",
 *         ref="#/components/schemas/SearchRecommendedGroups"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SearchRecommendedGroups",
 *     type="object",
 *     description="Рекомендации поиска по группам (`search_recommended_products`)",
 *     @OA\Property(
 *         property="main",
 *         type="array",
 *         description="Основной блок, до 3 SKU",
 *         @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *     ),
 *     @OA\Property(
 *         property="directions",
 *         type="object",
 *         description="До 2 SKU на направление, ключ — slug направления",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *         )
 *     ),
 *     @OA\Property(
 *         property="categories",
 *         type="object",
 *         description="До 2 SKU на категорию, ключ — slug/url_slug категории",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *         )
 *     ),
 *     @OA\Property(
 *         property="subcategories",
 *         type="object",
 *         description="До 2 SKU на подкатегорию, ключ — slug/url_slug подкатегории",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SearchOftenSearchedItem",
 *     type="object",
 *     description="Элемент блока «Часто ищут» в autocomplete. Приоритет: подкатегория → коллекция → частый запрос из админки. Partial match от 3 символов.",
 *     required={"label"},
 *     @OA\Property(property="label", type="string", example="Прямой диван"),
 *     @OA\Property(property="href", type="string", nullable=true, example="/catalog?subcategory=pryamoy-divan", description="Ссылка на каталог; null для статичных frequent без URL")
 * )
 *
 * @OA\Schema(
 *     schema="SearchCategoryFoundItem",
 *     type="object",
 *     description="Элемент блока «Найдено в категориях»: подкатегория, в которой найдены товары по текущему запросу.",
 *     required={"id","label","href","matchCount","products"},
 *     @OA\Property(property="id", type="string", example="pryamoy-divan", description="Slug подкатегории"),
 *     @OA\Property(property="label", type="string", example="Прямой диван"),
 *     @OA\Property(property="href", type="string", example="/catalog/a-plus/divany/pryamye"),
 *     @OA\Property(property="matchCount", type="integer", example=3, description="Число совпавших товаров в подкатегории"),
 *     @OA\Property(property="icon", type="string", nullable=true, example="search-sofa-straight"),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *         description="До 3 превью-SKU: сначала популярные (is_popular), затем остальные в порядке выдачи поиска",
 *         @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SearchProductsResponse",
 *     type="object",
 *     required={"query","correction","matchType","matchedQuery","items","meta"},
 *     @OA\Property(property="query", type="string"),
 *     @OA\Property(property="correction", type="string", nullable=true),
 *     @OA\Property(property="matchType", type="string", enum={"exact","cascade","similar","none"}),
 *     @OA\Property(property="matchedQuery", type="string", nullable=true),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CatalogSearchProduct")),
 *     @OA\Property(property="meta", ref="#/components/schemas/CatalogProductsMeta")
 * )
 *
 * @OA\Schema(
 *     schema="SearchIndexResponse",
 *     type="object",
 *     description="Ответ autocomplete-поиска. Индекс строится по title, subtitle, description, типу, коллекции, материалам, габаритам и полям связанной модели. Опечатки и короткие префиксы корректируются через словарь каталога (`correction`).",
 *     required={"query","correction","matchType","matchedQuery","products","oftenSearched","categoriesFound"},
 *     @OA\Property(property="query", type="string", example="диван", description="Нормализованный запрос пользователя (trim, lower, ё→е)"),
 *     @OA\Property(property="correction", type="string", nullable=true, example="угловой диван артемида бежевый oskar 2", description="Исправленный запрос после token-fix; null если опечаток не было"),
 *     @OA\Property(property="matchType", type="string", enum={"exact","cascade","similar","none"}, example="exact", description="exact — полное совпадение title; cascade — по сокращённой фразе; similar — fuzzy; none — пусто"),
 *     @OA\Property(property="matchedQuery", type="string", nullable=true, example="угловой диван артемида бежевый oskar 2", description="Фраза, по которой найдены товары"),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *         description="Товары: кругами по одному SKU из каждой модели. Количество — параметр limit (default 5, max 20; иное — из запроса). Поиск от 3 символов. Кастом-SKU не входят.",
 *         @OA\Items(ref="#/components/schemas/CatalogSearchProduct")
 *     ),
 *     @OA\Property(
 *         property="oftenSearched",
 *         type="array",
 *         description="Блок «Часто ищут»: подкатегории и коллекции каталога + frequent из админки, совпадающие с q (от 3 символов)",
 *         @OA\Items(ref="#/components/schemas/SearchOftenSearchedItem")
 *     ),
 *     @OA\Property(
 *         property="categoriesFound",
 *         type="array",
 *         description="Блок «Найдено в категориях»: агрегация подкатегорий по всем совпавшим товарам, сортировка по matchCount desc",
 *         @OA\Items(ref="#/components/schemas/SearchCategoryFoundItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CartAddItemRequest",
 *     type="object",
 *     required={"productId"},
 *     @OA\Property(property="productId", type="string", description="slug товара (SKU)"),
 *     @OA\Property(property="quantity", type="integer", minimum=1, example=1)
 * )
 *
 * @OA\Schema(
 *     schema="CartUpdateItemRequest",
 *     type="object",
 *     required={"quantity"},
 *     @OA\Property(property="quantity", type="integer", minimum=1, example=2)
 * )
 *
 * @OA\Schema(
 *     schema="CartLineItem",
 *     type="object",
 *     required={"productId","title","quantity","custom","image"},
 *     @OA\Property(property="productId", type="string", description="slug товара (SKU)"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="retailPrice", type="integer", nullable=true, description="Розничная цена каталога за единицу"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера или по акции за единицу"),
 *     @OA\Property(property="priceDisplay", type="string", nullable=true, description="Отформатированная розничная цена каталога для UI"),
 *     @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="omit при hasCatalogPromotion"),
 *     @OA\Property(property="hasCatalogPromotion", type="boolean", example=false, description="Строка под акцией catalog_promotions — промокод и кэшбек не применяются"),
 *     @OA\Property(property="cashbackEligible", type="boolean", example=true, description="К строке можно применить списание кэшбека (false при hasCatalogPromotion или для гостя)"),
 *     @OA\Property(property="catalogPromotion", ref="#/components/schemas/CatalogPromotionActive", nullable=true),
 *     @OA\Property(property="catalogPromotionDiscount", type="number", format="float", example=10000, description="retailLineTotal − lineTotal при акции"),
 *     @OA\Property(property="retailLineTotal", type="number", format="float", example=120000, description="retailPrice × quantity — сумма по рознице каталога"),
 *     @OA\Property(property="dealerDiscountAmount", type="number", format="float", example=12000, description="retailLineTotal − lineTotal"),
 *     @OA\Property(property="unitPrice", type="number", format="float", example=108000, description="Эффективная цена за единицу (акция или дилерская скидка)"),
 *     @OA\Property(property="lineTotal", type="number", format="float", example=108000, description="unitPrice × quantity (до промокода/кэшбека)"),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="comment", type="string", nullable=true, description="Комментарий к позиции (только дилер). Задаётся через PATCH /cart/items/{productId}/comment"),
 *     @OA\Property(
 *         property="attachment",
 *         ref="#/components/schemas/OrderAttachmentInfo",
 *         nullable=true,
 *         description="Файл позиции (только дилер). Загрузка: POST /cart/items/{productId}/attachment (multipart attachment). Скачивание: GET .../attachment"
 *     ),
 *     @OA\Property(property="custom", type="boolean", example=false, description="SKU «кастом» модели"),
 *     @OA\Property(property="catalogModelId", type="integer", nullable=true, example=12, description="ID модели каталога для promo scope"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage")
 * )
 *
 * @OA\Schema(
 *     schema="CartPromoInfo",
 *     type="object",
 *     required={"code","title","discountPercent"},
 *     @OA\Property(property="code", type="string", example="NOVINKA_ARTEMIDA", description="VYSTAVKA или NOVINKA_{slug коллекции}"),
 *     @OA\Property(property="title", type="string", example="NOVINKA_ARTEMIDA"),
 *     @OA\Property(property="discountPercent", type="number", format="float", example=10)
 * )
 *
 * @OA\Schema(
 *     schema="CartResponse",
 *     type="object",
 *     description="Цены: retailSubtotal — розница; subtotal — после дилерской/акционной цены по строкам; catalogPromotionDiscount — сумма экономии по акциям; promoDiscount только по строкам без hasCatalogPromotion; cashbackApplied — только по eligible subtotal (без акционных строк), cap 50%; total = subtotal − promo − cashback. Акция каталога, промокод и кэшбек не суммируются на одной строке.",
 *     required={"items","totalQuantity","retailSubtotal","dealerDiscountAmount","subtotal","promoDiscount","cashbackApplied","total","cashbackAvailable","discounts"},
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CartLineItem")
 *     ),
 *     @OA\Property(property="totalQuantity", type="integer", example=3),
 *     @OA\Property(property="retailSubtotal", type="number", format="float", example=240000, description="Сумма по розничным ценам"),
 *     @OA\Property(property="dealerDiscountAmount", type="number", format="float", example=24000, description="Сумма персональной скидки дилера"),
 *     @OA\Property(property="subtotal", type="number", format="float", example=216000, description="Сумма после дилерской скидки, до промо/кэшбека"),
 *     @OA\Property(property="promoDiscount", type="number", format="float", example=21600, description="Скидка по промокоду от subtotal"),
 *     @OA\Property(property="cashbackApplied", type="number", format="float", example=0, description="Списываемый кэшбек в корзине"),
 *     @OA\Property(property="total", type="number", format="float", example=194400, description="Итого к оплате (subtotal − promo − cashback)"),
 *     @OA\Property(property="promo", ref="#/components/schemas/CartPromoInfo", nullable=true, description="Применённый промокод (только дилер)"),
 *     @OA\Property(property="cashbackAvailable", type="number", format="float", example=5000, description="Доступный баланс кэшбека (только дилер)"),
 *     @OA\Property(property="catalogPromotionDiscount", type="number", format="float", example=15000, description="Сумма catalogPromotionDiscount по строкам"),
 *     @OA\Property(property="cashbackMaxApplicable", type="number", format="float", example=5000, description="min(баланс, 50% eligibleSubtotal − promo); eligible — строки без акции"),
 *     @OA\Property(property="discounts", ref="#/components/schemas/CheckoutDiscounts")
 * )
 *
 * @OA\Schema(
 *     schema="CheckoutDiscounts",
 *     type="object",
 *     required={"dealer","promo","cashback"},
 *     @OA\Property(
 *         property="dealer",
 *         type="object",
 *         required={"amount"},
 *         @OA\Property(property="amount", type="number", format="float", description="retailSubtotal − subtotal (скидка дилера и акции каталога)"),
 *         @OA\Property(property="personalAmount", type="number", format="float", nullable=true, description="Только персональная скидка дилера, без акций каталога")
 *     ),
 *     @OA\Property(
 *         property="promotion",
 *         type="object",
 *         nullable=true,
 *         description="Скидка по акциям catalog_promotions",
 *         @OA\Property(property="amount", type="number", format="float")
 *     ),
 *     @OA\Property(
 *         property="promo",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="amount", type="number", format="float"),
 *         @OA\Property(property="code", type="string", nullable=true)
 *     ),
 *     @OA\Property(
 *         property="cashback",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="amount", type="number", format="float")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CartItemCommentRequest",
 *     type="object",
 *     @OA\Property(property="comment", type="string", nullable=true, maxLength=2000, description="Пустая строка или null — удалить комментарий")
 * )
 *
 * @OA\Schema(
 *     schema="CartSyncRequest",
 *     type="object",
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CartSyncResponse",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer", description="Сколько позиций перенесено/объединено из гостевой корзины"),
 *     @OA\Property(property="resultTotal", type="integer", description="Итоговое количество единиц товара в корзине пользователя")
 * )
 *
 * @OA\Schema(
 *     schema="CartApplyPromoRequest",
 *     type="object",
 *     required={"code"},
 *     @OA\Property(property="code", type="string", example="VYSTAVKA", description="Код из «Мои бонусы». Custom без даты «Действует до» — действует, пока не использован.")
 * )
 *
 * @OA\Schema(
 *     schema="CartApplyCashbackRequest",
 *     type="object",
 *     @OA\Property(property="amount", type="number", format="float", nullable=true, example=5000, description="Сумма списания; если не указана — весь доступный баланс. Фактическое списание не превышает min(баланс, 50% subtotal корзины после промо)")
 * )
 *
 * @OA\Schema(
 *     schema="OrderLineItem",
 *     type="object",
 *     required={"productId","title","quantity","retailPrice","retailLineTotal","unitPrice","lineTotal","paidLineTotal","custom"},
 *     @OA\Property(property="productId", type="string", nullable=true, description="slug товара (SKU)"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="retailPrice", type="number", format="float", example=120000, description="Розничная цена за единицу на момент оформления (snapshot)"),
 *     @OA\Property(property="retailLineTotal", type="number", format="float", example=120000, description="retailPrice × quantity"),
 *     @OA\Property(property="dealerPrice", type="integer", nullable=true, description="Цена со скидкой дилера за единицу (snapshot)"),
 *     @OA\Property(property="dealerDiscountPercent", type="integer", nullable=true, description="Персональная скидка дилера, %; omit при hasCatalogPromotion"),
 *     @OA\Property(property="hasCatalogPromotion", type="boolean", example=false, description="На позицию действовала акция catalog_promotions"),
 *     @OA\Property(property="cashbackEligible", type="boolean", example=true, description="К позиции не списывался кэшбек при акции каталога"),
 *     @OA\Property(property="catalogPromotion", ref="#/components/schemas/CatalogPromotionActive", nullable=true),
 *     @OA\Property(property="catalogPromotionDiscount", type="number", format="float", example=10000, description="Экономия по акции каталога на строке"),
 *     @OA\Property(property="dealerPersonalDiscountAmount", type="number", format="float", example=12000, description="retailLineTotal − lineTotal без акции каталога"),
 *     @OA\Property(property="dealerDiscountAmount", type="number", format="float", example=12000, description="retailLineTotal − lineTotal"),
 *     @OA\Property(property="unitPrice", type="number", format="float", example=108000, description="Эффективная цена за единицу после дилерской скидки/акции"),
 *     @OA\Property(property="lineTotal", type="number", format="float", example=108000, description="unitPrice × quantity до промо/кэшбека"),
 *     @OA\Property(property="promoDiscountAmount", type="number", format="float", example=10800, description="Доля скидки промокода на позицию"),
 *     @OA\Property(property="cashbackUsedAmount", type="number", format="float", example=0, description="Доля списанного кэшбека на позицию"),
 *     @OA\Property(property="paidLineTotal", type="number", format="float", example=97200, description="Итог по позиции после всех скидок"),
 *     @OA\Property(property="custom", type="boolean", example=false, description="SKU «кастом» на момент оформления"),
 *     @OA\Property(
 *         property="comment",
 *         type="string",
 *         nullable=true,
 *         description="Комментарий к позиции (только дилер). Берётся из корзины или переопределяется в items[] при POST /orders"
 *     ),
 *     @OA\Property(
 *         property="attachment",
 *         ref="#/components/schemas/OrderAttachmentInfo",
 *         nullable=true,
 *         description="Файл позиции (только дилер, загружается при оформлении заказа)"
 *     ),
 *     @OA\Property(property="href", type="string", nullable=true, example="/product/kreslo-hyuston-belyy", description="Ссылка на карточку товара"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="specLine1", type="string", nullable=true, example="Ткань: велюр, цвет белый"),
 *     @OA\Property(property="specLine2", type="string", nullable=true, example="Коллекция Artemida")
 * )
 *
 * @OA\Schema(
 *     schema="OrderSummary",
 *     type="object",
 *     required={"number","status","statusLabel","uiStatus","totalAmount","itemsCount","images","extraCount","createdAt"},
 *     @OA\Property(property="number", type="string", example="ORD-20260819-A1B2C"),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"new","confirmed","production","ready","shipping","completed","cancelled"},
 *         example="new"
 *     ),
 *     @OA\Property(property="statusLabel", type="string", example="Новый"),
 *     @OA\Property(
 *         property="uiStatus",
 *         type="string",
 *         enum={"processing","in_work","delivery","completed","cancelled"},
 *         example="processing",
 *         description="Статус для UI ЛКД"
 *     ),
 *     @OA\Property(
 *         property="progressStep",
 *         type="integer",
 *         nullable=true,
 *         enum={1,2,3,4},
 *         example=1,
 *         description="Шаг прогресса заказа (null для отменённых)"
 *     ),
 *     @OA\Property(property="totalAmount", type="number", format="float", example=120000),
 *     @OA\Property(property="itemsCount", type="integer", example=3, description="Суммарное количество единиц по позициям"),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         description="До 3 mini-URL первых позиций заказа (srcSet.mini товара)",
 *         @OA\Items(type="string", example="/uploads/media/models/2026/08/sample_s.webp")
 *     ),
 *     @OA\Property(
 *         property="extraCount",
 *         type="integer",
 *         example=2,
 *         description="Число позиций сверх показанных в images (0, если позиций ≤3; аналог swatchCount в каталоге)"
 *     ),
 *     @OA\Property(property="createdAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="OrderListResponse",
 *     type="object",
 *     required={"items"},
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderSummary")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderCreateItemInput",
 *     type="object",
 *     required={"productId"},
 *     @OA\Property(
 *         property="productId",
 *         type="string",
 *         example="kreslo-hyuston-belyy",
 *         description="slug SKU (каталог). Обязателен всегда."
 *     ),
 *     @OA\Property(
 *         property="quantity",
 *         type="integer",
 *         minimum=1,
 *         example=2,
 *         description="Количество. **Обязателен** при создании заказа одним запросом (все элементы items[] с quantity). Не передаётся при оформлении из корзины — quantity берётся из корзины."
 *     ),
 *     @OA\Property(
 *         property="comment",
 *         type="string",
 *         nullable=true,
 *         maxLength=2000,
 *         example="Нужна другая фурнитура",
 *         description="Комментарий к позиции (только дилер). При atomic create — в items[]. При checkout из корзины — опциональная перезапись comment из корзины."
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderCreateRequest",
 *     type="object",
 *     required={"customerName","customerPhone"},
 *     description="JSON-создание заказа. Цена позиции (unitPrice) **не передаётся** — берётся из каталога. Для одного запроса без корзины передайте items[] с productId + quantity у каждой позиции.",
 *     @OA\Property(property="customerName", type="string", example="Иван Иванов", description="ФИО или название клиента"),
 *     @OA\Property(property="customerPhone", type="string", example="+79998886644", description="Телефон в любом формате"),
 *     @OA\Property(
 *         property="customerEmail",
 *         type="string",
 *         format="email",
 *         example="ivan@example.com",
 *         description="Необязательно; без email чек ЮKassa уходит на технический адрес guest+phone@fallback-домен"
 *     ),
 *     @OA\Property(property="deliveryAddress", type="string", nullable=true, example="Москва, ул. Пример, 1"),
 *     @OA\Property(
 *         property="paymentMethod",
 *         type="string",
 *         nullable=true,
 *         enum={"cash","cashless","online"},
 *         example="cashless",
 *         description="Способ оплаты: cash/cashless — дилер; online — гость (ЮKassa, выставляется сервером)"
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         minItems=1,
 *         description="Позиции заказа. **Режим A (рекомендуется):** каждый элемент с productId + quantity; comment опционален (дилер). **Режим B (legacy):** items без quantity — оформление из корзины, в items только comment для перезаписи.",
 *         @OA\Items(ref="#/components/schemas/OrderCreateItemInput")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderCreateMultipartRequest",
 *     type="object",
 *     required={"customerName","customerPhone"},
 *     description="multipart/form-data — customer-поля + items (JSON-строка или items[N][...]) + itemAttachments[productId] для файлов (дилер).",
 *     @OA\Property(property="customerName", type="string", example="Иван Иванов"),
 *     @OA\Property(property="customerPhone", type="string", example="+79998886644"),
 *     @OA\Property(property="customerEmail", type="string", nullable=true),
 *     @OA\Property(property="deliveryAddress", type="string", nullable=true),
 *     @OA\Property(
 *         property="paymentMethod",
 *         type="string",
 *         nullable=true,
 *         enum={"cash","cashless","online"},
 *         example="cashless"
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="string",
 *         example="[{'productId':'kreslo-hyuston-belyy','quantity':1,'comment':'Эскиз во вложении'}]",
 *         description="JSON-массив позиций OrderCreateItemInput[] или вложенные поля items[N][productId], items[N][quantity], items[N][comment]"
 *     ),
 *     @OA\Property(
 *         property="itemAttachments",
 *         type="object",
 *         description="Файлы позиций (только дилер). Имя поля: itemAttachments[{productId}] — slug из items[]. Не более 1 файла на позицию. Любой формат; max 50 МБ. Пример: itemAttachments[kreslo-hyuston-belyy].",
 *         @OA\AdditionalProperties(type="string", format="binary")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderPaymentStatusResponse",
 *     type="object",
 *     required={"number","status","statusLabel","paymentStatus","paymentReturnTarget"},
 *     @OA\Property(property="number", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="statusLabel", type="string"),
 *     @OA\Property(property="paymentStatus", type="string", enum={"pending","paid","cancelled","failed"}),
 *     @OA\Property(property="paymentStatusLabel", type="string", nullable=true),
 *     @OA\Property(property="paymentReturnTarget", type="string", enum={"success","cart"}, nullable=true),
 *     @OA\Property(property="paidAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="paymentExternalId", type="string", nullable=true, description="ID платежа в ЮKassa — для поиска в ЛК"),
 *     @OA\Property(property="paymentCartReturnUrl", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="YooKassaTestSimulateResponse",
 *     type="object",
 *     required={"ok","simulatedEvent","orderNumber","status","paymentStatus"},
 *     @OA\Property(property="ok", type="boolean", example=true),
 *     @OA\Property(property="simulatedEvent", type="string", example="payment.succeeded"),
 *     @OA\Property(property="scenario", type="string", nullable=true, example="insufficient_funds"),
 *     @OA\Property(property="orderNumber", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="paymentStatus", type="string"),
 *     @OA\Property(property="paymentExternalId", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="YooKassaWebhookNotification",
 *     type="object",
 *     required={"type","event","object"},
 *     description="Тело HTTP-уведомления ЮKassa. Сервер дополнительно проверяет платёж через GET /v3/payments/{id}.",
 *     @OA\Property(property="type", type="string", example="notification"),
 *     @OA\Property(
 *         property="event",
 *         type="string",
 *         enum={"payment.succeeded","payment.canceled","payment.waiting_for_capture","refund.succeeded"},
 *         example="payment.succeeded"
 *     ),
 *     @OA\Property(
 *         property="object",
 *         type="object",
 *         description="Объект платежа (или refund) на момент события",
 *         @OA\Property(property="id", type="string", example="22d6d597-000f-5000-9000-145f6df21d6f"),
 *         @OA\Property(property="status", type="string", example="succeeded"),
 *         @OA\Property(
 *             property="amount",
 *             type="object",
 *             @OA\Property(property="value", type="string", example="125000.00"),
 *             @OA\Property(property="currency", type="string", example="RUB")
 *         ),
 *         @OA\Property(
 *             property="metadata",
 *             type="object",
 *             @OA\Property(property="orderId", type="integer", example=42),
 *             @OA\Property(property="orderNumber", type="string", example="ORD-20260919-A1B2C")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderAttachmentInfo",
 *     type="object",
 *     required={"originalName","hasFile"},
 *     @OA\Property(property="originalName", type="string", nullable=true),
 *     @OA\Property(property="hasFile", type="boolean", example=true),
 *     @OA\Property(
 *         property="downloadUrl",
 *         type="string",
 *         nullable=true,
 *         example="/api/v1/cart/items/kreslo-hyuston-belyy/attachment",
 *         description="URL скачивания файла (в корзине или в деталях заказа)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderDocumentInfo",
 *     type="object",
 *     required={"id","label","url"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="label", type="string", example="Счёт на оплату"),
 *     @OA\Property(property="url", type="string", example="/api/v1/orders/ORD-20260819-A1B2C/documents/1")
 * )
 *
 * @OA\Schema(
 *     schema="OrderPromoInfo",
 *     type="object",
 *     required={"discountAmount","used"},
 *     @OA\Property(property="code", type="string", example="NOVINKA_ARTEMIDA", description="Код применённого промокода"),
 *     @OA\Property(property="title", type="string", example="NOVINKA_ARTEMIDA"),
 *     @OA\Property(property="discountAmount", type="number", format="float", example=24000),
 *     @OA\Property(property="used", type="boolean", example=true, description="Промокод помечен использованным для данного дилера после оформления заказа (used_at, used_order_id в dealer_promo_grants)"),
 *     @OA\Property(property="usedAt", type="string", format="date-time", nullable=true, example="2026-08-22T10:15:00+03:00"),
 *     @OA\Property(property="usedOrderId", type="integer", nullable=true, example=42, description="ID заказа, в котором промокод был использован")
 * )
 *
 * @OA\Schema(
 *     schema="OrderResponse",
 *     type="object",
 *     required={"number","status","statusLabel","uiStatus","customerName","customerPhone","retailSubtotalAmount","dealerDiscountAmount","subtotalAmount","promoDiscountAmount","cashbackUsedAmount","totalAmount","discounts","items","createdAt","updatedAt"},
 *     @OA\Property(property="number", type="string"),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending_payment","new","confirmed","production","ready","shipping","completed","cancelled"}
 *     ),
 *     @OA\Property(property="statusLabel", type="string"),
 *     @OA\Property(
 *         property="uiStatus",
 *         type="string",
 *         enum={"processing","in_work","delivery","completed","cancelled"}
 *     ),
 *     @OA\Property(property="progressStep", type="integer", nullable=true, enum={1,2,3,4}),
 *     @OA\Property(property="customerName", type="string"),
 *     @OA\Property(property="customerPhone", type="string"),
 *     @OA\Property(property="customerEmail", type="string", nullable=true),
 *     @OA\Property(property="deliveryAddress", type="string", nullable=true),
 *     @OA\Property(
 *         property="comment",
 *         type="string",
 *         nullable=true,
 *         deprecated=true,
 *         description="Устарело: комментарий к заказу. Для новых заказов всегда null — используйте comment в items[]"
 *     ),
 *     @OA\Property(
 *         property="attachment",
 *         ref="#/components/schemas/OrderAttachmentInfo",
 *         nullable=true,
 *         deprecated=true,
 *         description="Устарело: файл заказа. Для новых заказов null — используйте attachment в items[]"
 *     ),
 *     @OA\Property(property="retailSubtotalAmount", type="number", format="float", description="Сумма по розничным ценам на момент оформления"),
 *     @OA\Property(property="dealerDiscountAmount", type="number", format="float", description="retailSubtotalAmount − subtotalAmount"),
 *     @OA\Property(property="dealerPersonalDiscountAmount", type="number", format="float", description="Сумма персональной скидки дилера по строкам"),
 *     @OA\Property(property="catalogPromotionDiscountAmount", type="number", format="float", description="Сумма скидок по акциям catalog_promotions"),
 *     @OA\Property(property="subtotalAmount", type="number", format="float", description="Сумма после дилерской скидки/акций, до промокода/кэшбека"),
 *     @OA\Property(property="promoDiscountAmount", type="number", format="float", description="Скидка по промокоду от subtotalAmount"),
 *     @OA\Property(property="cashbackUsedAmount", type="number", format="float", description="Списано кэшбека"),
 *     @OA\Property(property="totalAmount", type="number", format="float", description="subtotal − promo − cashback + cashlessSurchargeAmount"),
 *     @OA\Property(property="discounts", ref="#/components/schemas/CheckoutDiscounts"),
 *     @OA\Property(property="promo", ref="#/components/schemas/OrderPromoInfo", nullable=true),
 *     @OA\Property(property="paymentMethod", type="string", nullable=true, enum={"cash","cashless","online"}),
 *     @OA\Property(property="paymentLabel", type="string", nullable=true, example="Безналичная оплата"),
 *     @OA\Property(
 *         property="paymentStatus",
 *         type="string",
 *         nullable=true,
 *         enum={"pending","paid","failed","cancelled"},
 *         description="Статус онлайн-оплаты (гость / ЮKassa)"
 *     ),
 *     @OA\Property(property="paymentProvider", type="string", nullable=true, example="yookassa"),
 *     @OA\Property(property="paidAt", type="string", nullable=true, format="date-time"),
 *     @OA\Property(
 *         property="paymentConfirmationUrl",
 *         type="string",
 *         nullable=true,
 *         description="URL оплаты ЮKassa — только в ответе POST /orders для гостя. Фронт делает redirect."
 *     ),
 *     @OA\Property(
 *         property="paymentReturnUrl",
 *         type="string",
 *         nullable=true,
 *         description="return_url в ЮKassa (обычно страница корзины с fromPayment=1 и number). Корзина очищается только после webhook payment.succeeded."
 *     ),
 *     @OA\Property(
 *         property="paymentExternalId",
 *         type="string",
 *         nullable=true,
 *         description="ID платежа в ЮKassa (гость) — для поиска в ЛК магазина"
 *     ),
 *     @OA\Property(
 *         property="paymentReceiptIncluded",
 *         type="boolean",
 *         nullable=true,
 *         description="Передан ли блок receipt в ЮKassa (54-ФЗ). false — риск сбоя на форме оплаты"
 *     ),
 *     @OA\Property(property="cashlessSurchargeAmount", type="number", format="float", example=0, description="Наценка за безналичную оплату"),
 *     @OA\Property(
 *         property="documents",
 *         type="array",
 *         description="Документы заказа (счёт, ОПД и др.), загружаются менеджером",
 *         @OA\Items(ref="#/components/schemas/OrderDocumentInfo")
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderLineItem")
 *     ),
 *     @OA\Property(property="createdAt", type="string", format="date-time"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="DealerAuthLoginRequest",
 *     type="object",
 *     required={"password"},
 *     @OA\Property(property="username", type="string", example="dealer001", description="Логин дилера (алиас: login)"),
 *     @OA\Property(property="login", type="string", example="dealer001", description="Алиас username"),
 *     @OA\Property(property="password", type="string", format="password", example="secret"),
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerAuthLoginResponse",
 *     type="object",
 *     required={"access_token","token_type","expires_in","profileComplete","guestSync"},
 *     @OA\Property(property="access_token", type="string", example="z4aMeWg3Hf..."),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="expires_in", type="integer", example=2592000),
 *     @OA\Property(property="profileComplete", type="boolean", example=false, description="false — нужно заполнить профиль через PUT /dealer/profile"),
 *     @OA\Property(property="guestSync", ref="#/components/schemas/GuestSyncResponse")
 * )
 *
 * @OA\Schema(
 *     schema="DealerAuthLogoutResponse",
 *     type="object",
 *     required={"ok"},
 *     @OA\Property(property="ok", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerProfileResponse",
 *     type="object",
 *     required={"id","subscription","username","companyName","dealerType","dealerTypeLabel","profileComplete","isBlocked"},
 *     @OA\Property(property="id", type="integer", example=10),
 *     @OA\Property(property="subscription", type="boolean", example=false, description="Подписка на персональные предложения и новости компании"),
 *     @OA\Property(property="username", type="string", example="dealer001"),
 *     @OA\Property(property="inn", type="string", nullable=true, example="7707083893"),
 *     @OA\Property(property="companyName", type="string", nullable=true, example="ООО Мебель"),
 *     @OA\Property(property="managerName", type="string", nullable=true, example="Иванов Иван"),
 *     @OA\Property(property="email", type="string", nullable=true, example="dealer@example.com"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="79998886644"),
 *     @OA\Property(property="dealerType", type="string", enum={"new","active"}, example="new"),
 *     @OA\Property(property="dealerTypeLabel", type="string", example="Новый"),
 *     @OA\Property(property="profileComplete", type="boolean", example=true),
 *     @OA\Property(property="isBlocked", type="boolean", example=false),
 *     @OA\Property(property="credentialsSentAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="firstLoginAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="assignedManager", ref="#/components/schemas/DealerAssignedManagerInfo", nullable=true, description="Менеджер фабрики (из админки или params по умолчанию)"),
 *     @OA\Property(property="personalDiscountPercent", type="number", format="float", nullable=true, example=12.5, description="Персональная скидка из админки; null — используется значение по умолчанию (0%)"),
 *     @OA\Property(property="effectiveDiscountPercent", type="number", format="float", example=0, description="Итоговая скидка для расчёта dealerPrice, %"),
 *     @OA\Property(property="priceList", ref="#/components/schemas/DealerPriceListFile", nullable=true, description="Актуальный прайс для ЛК: индивидуальный, если загружен; иначе общий"),
 *     @OA\Property(property="cashback", ref="#/components/schemas/DealerCashbackWidget", nullable=true, description="Баланс и прогресс кэшбека за текущий расчётный период"),
 *     @OA\Property(
 *         property="promotionBanners",
 *         type="array",
 *         description="Активные маркетинговые баннеры («В эфире»); те же данные, что GET /dealer/promotions/banners → items",
 *         @OA\Items(ref="#/components/schemas/DealerPromotionMarketingBlock")
 *     ),
 *     @OA\Property(
 *         property="catalogPromotions",
 *         type="array",
 *         description="Активные акции каталога; те же данные, что GET /dealer/promotions/sales → items",
 *         @OA\Items(ref="#/components/schemas/DealerCatalogPromotionItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DealerPriceListFile",
 *     type="object",
 *     required={"url","label","filename"},
 *     @OA\Property(property="url", type="string", example="/uploads/media/documents/price-list.xlsx"),
 *     @OA\Property(property="label", type="string", example="Прайс-лист"),
 *     @OA\Property(property="filename", type="string", example="price-list.xlsx"),
 *     @OA\Property(property="mimeType", type="string", nullable=true, example="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerPriceListResponse",
 *     type="object",
 *     description="Раздельный список прайсов. В GET /dealer/profile поле priceList — уже выбранный актуальный (personal ?? common).",
 *     @OA\Property(property="common", ref="#/components/schemas/DealerPriceListFile", nullable=true, description="Общий прайс для всех дилеров"),
 *     @OA\Property(property="personal", ref="#/components/schemas/DealerPriceListFile", nullable=true, description="Индивидуальный прайс дилера")
 * )
 *
 * @OA\Schema(
 *     schema="DealerModelTechPhotoFolderItem",
 *     type="object",
 *     required={"label","url"},
 *     @OA\Property(property="label", type="string", example="Адриано", description="Наименование коллекции мебели"),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         example="https://drive.google.com/drive/folders/abc123",
 *         description="Ссылка на облачную папку с тех. фото габаритов"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DealerModelTechPhotosResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         nullable=true,
 *         description="Общая ссылка, если у всех коллекций в items одна и та же папка; иначе null"
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DealerModelTechPhotoFolderItem")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         required={"total"},
 *         @OA\Property(property="total", type="integer", example=42)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DealerPasswordChangeRequest",
 *     type="object",
 *     required={"currentPassword","newPassword"},
 *     @OA\Property(property="currentPassword", type="string", format="password"),
 *     @OA\Property(property="newPassword", type="string", format="password", minLength=8)
 * )
 *
 * @OA\Schema(
 *     schema="DealerPasswordChangeResponse",
 *     type="object",
 *     required={"ok"},
 *     @OA\Property(property="ok", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerAssignedManagerInfo",
 *     type="object",
 *     required={"name","role"},
 *     @OA\Property(property="name", type="string", example="Анна Петрова"),
 *     @OA\Property(property="role", type="string", example="Менеджер заказов"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+79991234567"),
 *     @OA\Property(property="email", type="string", nullable=true, example="manager@mebelanna.ru"),
 *     @OA\Property(property="hours", type="string", nullable=true, example="Пн–Пт 9:00–18:00"),
 *     @OA\Property(
 *         property="avatar",
 *         ref="#/components/schemas/CatalogImage",
 *         nullable=true,
 *         description="Фото менеджера (если настроено)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DealerProfileUpdateRequest",
 *     type="object",
 *     required={"inn","managerName","email","phone"},
 *     @OA\Property(property="inn", type="string", example="7707083893", description="10 или 12 цифр"),
 *     @OA\Property(property="managerName", type="string", example="Иванов Иван"),
 *     @OA\Property(property="email", type="string", format="email", example="dealer@example.com"),
 *     @OA\Property(property="phone", type="string", example="79998886644")
 * )
 *
 * @OA\Schema(
 *     schema="DealerPromoBonus",
 *     type="object",
 *     required={"id","code","title","discountPercent"},
 *     @OA\Property(property="id", type="integer", example=5, description="ID выдачи — для применения в корзине передаётся code"),
 *     @OA\Property(property="code", type="string", example="NOVINKA_ARTEMIDA"),
 *     @OA\Property(property="title", type="string", example="NOVINKA_ARTEMIDA"),
 *     @OA\Property(property="discountPercent", type="number", format="float", example=10),
 *     @OA\Property(property="expiresAt", type="string", format="date-time", nullable=true, description="Срок выдачи; null — без ограничения (custom без «Действует до»)"),
 *     @OA\Property(property="catalogCollectionSlug", type="string", nullable=true, example="artemida", description="Slug коллекции для промокодов NOVINKA_{коллекция}"),
 *     @OA\Property(property="catalogModelId", type="integer", nullable=true, example=12, description="ID модели-новинки, при сохранении которой выдан промокод")
 * )
 *
 * @OA\Schema(
 *     schema="DealerCashbackWidget",
 *     type="object",
 *     required={"balance","periodTotal","periodYearMonth","currentPercent","amountToNextThreshold","progress","nextExpiringAmount","nextExpiringAt"},
 *     @OA\Property(property="balance", type="number", format="float", example=15000, description="Доступный баланс"),
 *     @OA\Property(property="periodTotal", type="number", format="float", example=500000, description="Сумма заказов в текущем периоде"),
 *     @OA\Property(property="periodYearMonth", type="string", example="2026-08"),
 *     @OA\Property(property="currentPercent", type="number", format="float", example=1.5, description="Текущий процент начисления"),
 *     @OA\Property(property="nextThreshold", type="number", format="float", nullable=true, example=1000000),
 *     @OA\Property(property="nextPercent", type="number", format="float", nullable=true, example=2),
 *     @OA\Property(property="amountToNextThreshold", type="number", format="float", nullable=true, example=759412, description="Оборот до следующего порога (nextThreshold − periodTotal); null на максимальной ступени"),
 *     @OA\Property(property="progress", type="number", format="float", example=0.5, description="Прогресс до следующего порога, 0–1"),
 *     @OA\Property(property="nextExpiringAmount", type="number", format="float", nullable=true, example=5000, description="Сумма кэшбека, которая сгорит в ближайшую дату (FIFO по начислениям)"),
 *     @OA\Property(property="nextExpiringAt", type="string", format="date-time", nullable=true, example="2026-12-01T12:00:00+03:00", description="Дата и время сгорания ближайшей порции (expires_at начисления)")
 * )
 *
 * @OA\Schema(
 *     schema="DealerPromotionMarketingBlock",
 *     type="object",
 *     required={"id","headline"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="headline", type="string", example="Скидка на коллекцию"),
 *     @OA\Property(property="bodyText", type="string", nullable=true),
 *     @OA\Property(property="priceCurrent", type="string", nullable=true, example="159 800 ₽"),
 *     @OA\Property(property="priceOld", type="string", nullable=true, example="320 000 ₽"),
 *     @OA\Property(property="ctaLabel", type="string", nullable=true, example="Подробнее"),
 *     @OA\Property(property="ctaUrl", type="string", nullable=true),
 *     @OA\Property(property="promoLabel", type="string", nullable=true),
 *     @OA\Property(property="promoCode", type="string", nullable=true, example="SPRING10"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="validFrom", type="string", format="date", nullable=true),
 *     @OA\Property(property="validTo", type="string", format="date", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerPromotionBannersResponse",
 *     type="object",
 *     required={"items"},
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         description="Все баннеры со статусом «В эфире» (valid_from/valid_to относительно текущей даты). Порядок: id по убыванию (новые первыми). Пустой массив, если нет активных.",
 *         @OA\Items(ref="#/components/schemas/DealerPromotionMarketingBlock")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DealerPromotionPopupResponse",
 *     type="object",
 *     required={"items","popup"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/DealerPromotionMarketingBlock")),
 *     @OA\Property(property="popup", ref="#/components/schemas/DealerPromotionMarketingBlock", nullable=true, description="Первый элемент items; для совместимости со старым фронтом")
 * )
 *
 * @OA\Schema(
 *     schema="DealerCatalogPromotionItem",
 *     type="object",
 *     required={"id","title","discountType","discountValue","startsAt","endsAt","scopeType","catalogModelId"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="discountType", type="string", enum={"percent","fixed_amount"}),
 *     @OA\Property(property="discountValue", type="number", format="float"),
 *     @OA\Property(property="startsAt", type="string", format="date-time"),
 *     @OA\Property(property="endsAt", type="string", format="date-time"),
 *     @OA\Property(property="scopeType", type="string", enum={"model","product"}),
 *     @OA\Property(property="catalogModelId", type="integer"),
 *     @OA\Property(property="catalogModelTitle", type="string", nullable=true),
 *     @OA\Property(property="catalogProductId", type="integer", nullable=true),
 *     @OA\Property(property="catalogProductSlug", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DealerCatalogPromotionsResponse",
 *     type="object",
 *     required={"items"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/DealerCatalogPromotionItem"))
 * )
 *
 * @OA\Schema(
 *     schema="DealerBonusesResponse",
 *     type="object",
 *     required={"promos","cashback"},
 *     @OA\Property(
 *         property="promos",
 *         type="array",
 *         description="Активные неиспользованные промокоды",
 *         @OA\Items(ref="#/components/schemas/DealerPromoBonus")
 *     ),
 *     @OA\Property(property="cashback", ref="#/components/schemas/DealerCashbackWidget")
 * )
 *
 * @OA\Schema(
 *     schema="ApiValidationError",
 *     type="object",
 *     required={"message","detail"},
 *     @OA\Property(property="message", type="string", example="Промокод недоступен или уже использован."),
 *     @OA\Property(property="detail", type="string"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         nullable=true,
 *         description="Поле → список сообщений об ошибках (ключ — имя поля, значение — массив строк)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProfileIncompleteError",
 *     type="object",
 *     required={"message","detail","code"},
 *     @OA\Property(property="message", type="string", example="Заполните профиль дилера."),
 *     @OA\Property(property="detail", type="string"),
 *     @OA\Property(property="code", type="string", example="PROFILE_INCOMPLETE")
 * )
 *
 * @OA\Schema(
 *     schema="LeadType",
 *     type="string",
 *     description="Тип заявки (slug → подпись в админке): contacts — Контакты; faq — FAQ; partners — Партнер; designers — Дизайнер; vacancy — Вакансия",
 *     enum={"contacts","faq","partners","designers","vacancy"},
 *     example="contacts"
 * )
 *
 * @OA\Schema(
 *     schema="LeadCreateRequest",
 *     type="object",
 *     required={"type","name","consent"},
 *     description="Набор полей зависит от type. Отправка: application/json или multipart/form-data (поле attachment — PDF, doc, docx, xls, xlsx).",
 *     @OA\Property(property="type", ref="#/components/schemas/LeadType"),
 *     @OA\Property(property="name", type="string", example="Иван Иванов", description="ФИО"),
 *     @OA\Property(property="email", type="string", format="email", nullable=true, example="user@example.com", description="Обязателен для vacancy"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+79894232000", description="E.164 (+7 и 10 цифр). Обязателен для contacts, faq, partners, designers"),
 *     @OA\Property(property="comment", type="string", nullable=true, description="Комментарий: contacts, faq, partners, vacancy"),
 *     @OA\Property(property="consent", type="boolean", example=true, description="Согласие на обработку персональных данных (должно быть true)"),
 *     @OA\Property(property="studio", type="string", nullable=true, description="Только designers — студия"),
 *     @OA\Property(property="portfolio", type="string", format="uri", nullable=true, description="Только designers — ссылка на портфолио"),
 *     @OA\Property(property="city", type="string", nullable=true, description="Только designers — город"),
 *     @OA\Property(property="vacancy_slug", type="string", nullable=true, example="manager-rostov", description="Только vacancy — slug вакансии"),
 *     @OA\Property(property="vacancy_title", type="string", nullable=true, example="Менеджер по продажам", description="Только vacancy — название вакансии"),
 *     @OA\Property(property="resume_name", type="string", nullable=true, description="Legacy: имя файла резюме; при multipart предпочтительно attachment")
 * )
 *
 * @OA\Schema(
 *     schema="LeadCreateResponse",
 *     type="object",
 *     required={"ok","id"},
 *     @OA\Property(property="ok", type="boolean", example=true),
 *     @OA\Property(property="id", type="integer", example=42)
 * )
 *
 * @OA\Schema(
 *     schema="GuestSessionId",
 *     type="string",
 *     description="Стабильный ID гостевой сессии. Генерируется на фронте один раз (UUID), хранится в браузере. Передаётся в заголовке X-Session-ID и/или в поле sessionId.",
 *     example="7f3c2a9e1b0046d8a2f59c8e4d1a003b"
 * )
 *
 * @OA\Schema(
 *     schema="GuestSyncFavoritesResult",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer", description="Сколько товаров добавлено из гостевого избранного"),
 *     @OA\Property(property="resultTotal", type="integer", description="Итоговое число товаров в избранном пользователя")
 * )
 *
 * @OA\Schema(
 *     schema="GuestSyncMoodboardsResult",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer"),
 *     @OA\Property(property="resultTotal", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="GuestSyncCartResult",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer", description="Сколько позиций перенесено/объединено из гостевой корзины"),
 *     @OA\Property(property="resultTotal", type="integer", description="Итоговое количество единиц товара в корзине пользователя")
 * )
 *
 * @OA\Schema(
 *     schema="GuestSyncResponse",
 *     type="object",
 *     required={"skipped"},
 *     description="Результат автоматического объединения избранного, корзины и гостевых мудбордов при auth. Повторный sync идемпотентен.",
 *     @OA\Property(property="skipped", type="boolean", description="true — sessionId не передан ни в заголовке, ни в теле"),
 *     @OA\Property(property="reason", type="string", nullable=true, example="no_session"),
 *     @OA\Property(property="favorites", ref="#/components/schemas/GuestSyncFavoritesResult", description="Присутствует, если skipped=false"),
 *     @OA\Property(property="cart", ref="#/components/schemas/GuestSyncCartResult", description="Присутствует, если skipped=false"),
 *     @OA\Property(property="moodboards", ref="#/components/schemas/GuestSyncMoodboardsResult", description="Присутствует, если skipped=false")
 * )
 *
 * @OA\Schema(
 *     schema="FavoriteProductRequest",
 *     type="object",
 *     required={"productId"},
 *     @OA\Property(property="productId", type="string", description="slug товара (SKU)"),
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true, description="Для гостя, если не передан X-Session-ID")
 * )
 *
 * @OA\Schema(
 *     schema="FavoriteActionResponse",
 *     type="object",
 *     required={"productId","isFavorite"},
 *     @OA\Property(property="productId", type="string"),
 *     @OA\Property(property="isFavorite", type="boolean")
 * )
 *
 * @OA\Schema(
 *     schema="FavoriteCheckRequest",
 *     type="object",
 *     required={"productIds"},
 *     @OA\Property(property="productIds", type="array", @OA\Items(type="string"), description="slug товаров"),
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="FavoriteCheckResponse",
 *     type="object",
 *     required={"favorites"},
 *     @OA\Property(
 *         property="favorites",
 *         type="object",
 *         additionalProperties=@OA\Schema(type="boolean"),
 *         example={"divan-artemida-seryy": true, "kreslo-hyuston-belyy": false}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="FavoriteListItem",
 *     type="object",
 *     required={"product"},
 *     @OA\Property(property="product", ref="#/components/schemas/CatalogProductCard")
 * )
 *
 * @OA\Schema(
 *     schema="FavoritesListResponse",
 *     type="object",
 *     required={"page","pages","total","items"},
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="pages", type="integer", example=1),
 *     @OA\Property(property="total", type="integer", example=3),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/FavoriteListItem"))
 * )
 *
 * @OA\Schema(
 *     schema="FavoritesSyncRequest",
 *     type="object",
 *     description="Ручное объединение после входа. Достаточно sessionId в теле или заголовка X-Session-ID. Требуется Bearer.",
 *     @OA\Property(property="sessionId", ref="#/components/schemas/GuestSessionId")
 * )
 *
 * @OA\Schema(
 *     schema="FavoritesSyncResponse",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer"),
 *     @OA\Property(property="resultTotal", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerBootstrapResponse",
 *     type="object",
 *     required={"categories","colors","models","fabrics","surfaceMaterials","objectTypes"},
 *     @OA\Property(property="categories", ref="#/components/schemas/MoodboardPickerCategoriesResponse"),
 *     @OA\Property(property="colors", ref="#/components/schemas/MoodboardPickerColorsResponse"),
 *     @OA\Property(property="models", ref="#/components/schemas/MoodboardPickerModelsResponse"),
 *     @OA\Property(property="fabrics", ref="#/components/schemas/MoodboardPickerFabricsResponse"),
 *     @OA\Property(property="surfaceMaterials", ref="#/components/schemas/MoodboardPickerSurfaceMaterialsResponse"),
 *     @OA\Property(
 *         property="objectTypes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MoodboardObjectTypeItem")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerMeta",
 *     type="object",
 *     required={"total","page","limit"},
 *     @OA\Property(property="total", type="integer"),
 *     @OA\Property(property="page", type="integer"),
 *     @OA\Property(property="limit", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerCategoryItem",
 *     type="object",
 *     required={"slug","label","sortOrder"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="sortOrder", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerCategoriesResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardPickerCategoryItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerColorItem",
 *     type="object",
 *     required={"id","label"},
 *     @OA\Property(property="id", type="string", description="slug catalog_colors"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="hexColor", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerColorsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardPickerColorItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerCollectionRef",
 *     type="object",
 *     required={"slug","title"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="title", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerTaxonomyRef",
 *     type="object",
 *     required={"slug","label"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="label", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerModelItem",
 *     type="object",
 *     required={"slug","title","angles","moodboardPhotos"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="collection", ref="#/components/schemas/MoodboardPickerCollectionRef", nullable=true),
 *     @OA\Property(property="category", ref="#/components/schemas/MoodboardPickerTaxonomyRef", nullable=true),
 *     @OA\Property(property="angles", type="array", description="Ракурсы модели (галерея ракурсов)", @OA\Items(ref="#/components/schemas/CatalogImage")),
 *     @OA\Property(property="previewImage", ref="#/components/schemas/CatalogImage", nullable=true, description="Первый ракурс"),
 *     @OA\Property(property="moodboardPhotos", type="array", description="Фото для мудборда из админки модели (purpose=interior)", @OA\Items(ref="#/components/schemas/CatalogImage"))
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerModelsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardPickerModelItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerFabricItem",
 *     type="object",
 *     required={"fabricColorId","slug","label"},
 *     @OA\Property(property="fabricColorId", type="integer"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="collection", type="string", nullable=true),
 *     @OA\Property(property="designCode", type="string", nullable=true),
 *     @OA\Property(property="colorId", type="string", nullable=true),
 *     @OA\Property(property="colorName", type="string", nullable=true),
 *     @OA\Property(property="hexColor", type="string", nullable=true),
 *     @OA\Property(property="texture", type="string", nullable=true),
 *     @OA\Property(property="isRecommendedFabric", type="boolean"),
 *     @OA\Property(property="positionNumber", type="integer", nullable=true),
 *     @OA\Property(property="swatch", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerFabricsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardPickerFabricItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerSurfaceMaterialItem",
 *     type="object",
 *     required={"slug","name","materialType"},
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="materialType", type="string"),
 *     @OA\Property(property="photo", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="texture", ref="#/components/schemas/CatalogImage", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPickerSurfaceMaterialsResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardPickerSurfaceMaterialItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardObjectTypeItem",
 *     type="object",
 *     required={"code","label","schemaVersion"},
 *     @OA\Property(property="code", type="string", enum={"model","fabric","surface_material","product"}),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="schemaVersion", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardObjectTypesResponse",
 *     type="object",
 *     required={"items"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardObjectTypeItem"))
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardCanvas",
 *     type="object",
 *     @OA\Property(property="width", type="integer", nullable=true),
 *     @OA\Property(property="height", type="integer", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardItemGeometry",
 *     type="object",
 *     required={"width","height","x","y"},
 *     @OA\Property(property="width", type="number", format="float"),
 *     @OA\Property(property="height", type="number", format="float"),
 *     @OA\Property(property="x", type="number", format="float"),
 *     @OA\Property(property="y", type="number", format="float"),
 *     @OA\Property(property="zIndex", type="integer"),
 *     @OA\Property(property="rotation", type="number", format="float")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardSaveItem",
 *     type="object",
 *     required={"objectType","geometry"},
 *     @OA\Property(property="objectType", type="string", enum={"model","fabric","surface_material","product"}),
 *     @OA\Property(property="refSlug", type="string", nullable=true, description="slug модели, surface_material или product (для fabric не используется)"),
 *     @OA\Property(property="refId", type="integer", nullable=true, description="Обязателен для objectType=fabric — ID catalog_fabric_collection_colors (fabricColorId из picker)"),
 *     @OA\Property(property="geometry", ref="#/components/schemas/MoodboardItemGeometry"),
 *     @OA\Property(property="meta", type="object", nullable=true, additionalProperties=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardSaveComment",
 *     type="object",
 *     required={"text","x","y"},
 *     @OA\Property(property="text", type="string", maxLength=2000),
 *     @OA\Property(property="x", type="number", format="float"),
 *     @OA\Property(property="y", type="number", format="float")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardCoverRef",
 *     type="object",
 *     @OA\Property(property="mediaId", type="integer", nullable=true, description="ID media_files после POST /moodboard/uploads/cover; null — снять обложку")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardCoverUploadResponse",
 *     type="object",
 *     required={"mediaId","image"},
 *     @OA\Property(property="mediaId", type="integer"),
 *     @OA\Property(property="image", ref="#/components/schemas/CatalogImage")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardCreateRequest",
 *     type="object",
 *     required={"title"},
 *     description="JSON-тело POST: обложка через cover.mediaId (после uploads/cover) или multipart/form-data с файлом cover.",
 *     @OA\Property(property="title", type="string", maxLength=255),
 *     @OA\Property(property="cover", ref="#/components/schemas/MoodboardCoverRef", nullable=true),
 *     @OA\Property(property="canvas", ref="#/components/schemas/MoodboardCanvas", nullable=true),
 *     @OA\Property(property="items", type="array", maxItems=200, @OA\Items(ref="#/components/schemas/MoodboardSaveItem")),
 *     @OA\Property(property="comments", type="array", maxItems=100, @OA\Items(ref="#/components/schemas/MoodboardSaveComment"))
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardContentSaveRequest",
 *     type="object",
 *     description="PUT: содержимое мудборда без смены названия. items/comments — полная замена при передаче ключа. Обложка — cover.mediaId, multipart cover, или removeCover.",
 *     @OA\Property(property="canvas", ref="#/components/schemas/MoodboardCanvas", nullable=true),
 *     @OA\Property(property="cover", ref="#/components/schemas/MoodboardCoverRef", nullable=true),
 *     @OA\Property(property="removeCover", type="boolean", description="true/1 — удалить обложку"),
 *     @OA\Property(property="items", type="array", maxItems=200, @OA\Items(ref="#/components/schemas/MoodboardSaveItem")),
 *     @OA\Property(property="comments", type="array", maxItems=100, @OA\Items(ref="#/components/schemas/MoodboardSaveComment"))
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardPatchTitleRequest",
 *     type="object",
 *     required={"title"},
 *     @OA\Property(property="title", type="string", maxLength=255)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardAuthor",
 *     type="object",
 *     required={"id","displayName"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="displayName", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardShareFields",
 *     type="object",
 *     @OA\Property(property="shareCode", type="string", nullable=true, description="Код для GET /moodboard/public/{shareCode}; у гостевых мудбордов"),
 *     @OA\Property(property="shareUrl", type="string", nullable=true, description="Страница на фронте (frontendUrl + moodboardSharePathPrefix)"),
 *     @OA\Property(property="publicApiUrl", type="string", nullable=true, description="Путь API публичного просмотра")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardSyncResponse",
 *     type="object",
 *     required={"mergedCount","resultTotal"},
 *     @OA\Property(property="mergedCount", type="integer"),
 *     @OA\Property(property="resultTotal", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardDetailItem",
 *     type="object",
 *     required={"id","objectType","refSlug","refId","geometry"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="objectType", type="string"),
 *     @OA\Property(property="refSlug", type="string"),
 *     @OA\Property(property="refId", type="integer"),
 *     @OA\Property(property="geometry", ref="#/components/schemas/MoodboardItemGeometry"),
 *     @OA\Property(property="ref", type="object", nullable=true, description="Snapshot данных каталога"),
 *     @OA\Property(property="meta", type="object", nullable=true, additionalProperties=true)
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardDetailComment",
 *     type="object",
 *     required={"id","text","x","y","createdAt"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="text", type="string"),
 *     @OA\Property(property="x", type="number", format="float"),
 *     @OA\Property(property="y", type="number", format="float"),
 *     @OA\Property(property="createdAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardDetailResponse",
 *     type="object",
 *     required={"id","title","status","items","comments","createdAt","updatedAt"},
 *     @OA\Property(property="id", type="string", description="public_id"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}, description="Read-only в API; при создании всегда draft, смена статуса пока не exposed"),
 *     @OA\Property(property="author", ref="#/components/schemas/MoodboardAuthor", nullable=true, description="null для гостевого автора до merge"),
 *     @OA\Property(property="shareCode", type="string", nullable=true),
 *     @OA\Property(property="shareUrl", type="string", nullable=true),
 *     @OA\Property(property="publicApiUrl", type="string", nullable=true),
 *     @OA\Property(property="cover", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="canvas", ref="#/components/schemas/MoodboardCanvas", nullable=true),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardDetailItem")),
 *     @OA\Property(property="comments", type="array", @OA\Items(ref="#/components/schemas/MoodboardDetailComment")),
 *     @OA\Property(property="createdAt", type="string", format="date-time"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardListItem",
 *     type="object",
 *     required={"id","title","status","updatedAt","createdAt"},
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="status", type="string", description="Read-only; см. MoodboardDetailResponse.status"),
 *     @OA\Property(property="cover", ref="#/components/schemas/CatalogImage", nullable=true),
 *     @OA\Property(property="shareCode", type="string", nullable=true),
 *     @OA\Property(property="shareUrl", type="string", nullable=true),
 *     @OA\Property(property="publicApiUrl", type="string", nullable=true),
 *     @OA\Property(property="updatedAt", type="string", format="date-time"),
 *     @OA\Property(property="createdAt", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="MoodboardListResponse",
 *     type="object",
 *     required={"items","meta"},
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MoodboardListItem")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MoodboardPickerMeta")
 * )
 *
 */
class OpenApiSpec
{
}
