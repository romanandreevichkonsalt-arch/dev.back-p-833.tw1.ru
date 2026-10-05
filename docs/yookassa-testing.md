# ЮKassa: тестовый магазин и dev-методы оплаты

## 1. Демо-магазин вместо live

1. [ЛК ЮKassa](https://yookassa.ru/my) → **Настройки** → создайте **тестовый/демо-магазин** (до 20 штук). Подробнее: [Тестовые магазины](https://yookassa.ru/docs/support/merchant/payments/implement/test-store).
2. **Переключитесь на этот магазин** в списке сервисов **вверху ЛК** (не боевой!). Только после переключения открывайте [Интеграция → Ключи API](https://yookassa.ru/my/merchant/integration/api-keys).
3. У **тестового** магазина секретный ключ обычно уже **`test_…`** (для demo часто без SMS, в отличие от `live_`). Скопируйте **shopId** (например `1470173`) и ключ. Если видите только `live_…` — вы всё ещё в боевом магазине.
4. **Интеграция → HTTP-уведомления** для **этого же тестового магазина**:
   - URL: `https://dev.back-p-833.tw1.ru/api/v1/payments/yookassa/webhook`
   - События: `payment.succeeded`, `payment.canceled`
5. В `.env` на dev (и локально) замените live-ключи:

```bash
YOOKASSA_SHOP_ID=<shopId демо-магазина>
YOOKASSA_SECRET_KEY=test_xxxxxxxx
YOOKASSA_RETURN_URL=https://dev.front-p-833.tw1.ru/cart?fromPayment=1
# опционально — защита test API:
# YOOKASSA_TEST_API_TOKEN=случайная_строка
```

`deploy` **не копирует** `.env` — правки только на сервере в  
`/var/www/dev_back_p_8_usr/data/app/dev.back-p-833.tw1.ru/.env`.

После смены ключей перезапуск PHP не обязателен (env читается при запросе).

### 1.1. Что говорит документация ЮKassa (и почему вы видите только `live_`)

Источники:

- [Тестовые магазины](https://yookassa.ru/docs/support/merchant/payments/implement/test-store) — отдельный **демо-магазин**, свой shopId, ключ берётся в **Интеграция → Ключи API** («выпустите секретный ключ»).
- [Формат API](https://yookassa.ru/developers/using-api/interaction-format) — для **демо** ключ «выпускается автоматически и всегда доступен в ЛК»; для **боевого** — SMS и одноразовый показ при выпуске.
- [Секретный ключ](https://yookassa.ru/docs/support/merchant/payments/implement/keys) — полный ключ показывается **только в момент выпуска/перевыпуска**; потом в списке только маска `live_****` / `test_****`.

**Отдельной кнопки «создать тестовый ключ» у боевого магазина нет.** Префикс `test_` бывает только у ключа **демо-магазина**, не у `live_`.

| Что видите в «Ключи API» | Что это значит |
|--------------------------|----------------|
| Маска `live_…ZhE0`, иконки перевыпуска/удаления | Вы в **боевом** магазине. Это не тестовый ключ. |
| Кнопка **«Выпустить ключ»** (ключа ещё нет) | Часто **демо-магазин** или новый магазин — после SMS откроется окно с **полным** ключом (скопировать сразу). |
| Маска `test_****` | Демо-магазин, ключ уже выпускали. Полную строку не покажут — только **перевыпустить** (SMS) и скопировать из окна. |

**Проверка, что `1470173` — именно demo:** переключитесь на этот магазин вверху ЛК → **Настройки → Магазин** → поле **shopId**. Затем снова **Интеграция → Ключи API**. Если shopId совпадает, но маска всё равно `live_` — это **не демо-магазин** (второй боевой или ошибка переключателя).

**Если демо-магазина нет в списке:** блок с названием магазина вверху → **Добавить магазин** → тип **демонстрационный / тестовый** ([инструкция](https://yookassa.ru/docs/support/merchant/payments/implement/test-store)). Появится через ~минуту. Не путать с основным магазином после договора.

**Если ключ выпустили и закрыли окно без копирования:** в ЛК останется только маска. Нужен **перевыпуск** (стрелка обновления) → SMS → **скопировать ключ из модального окна** (единственный показ).

**Пока нет `test_`, но нужно тестировать бэкенд на dev:** в `.env` можно временно `YOOKASSA_TEST_ENDPOINTS=1` — заработают `POST /api/v1/payments/yookassa/test/succeed|cancel|…` (симуляция webhook). Форма ЮKassa и тестовые карты без пары shopId + `test_` всё равно не заработают.

## 2. Два способа проверить оплату

### A. Реальная тестовая форма ЮKassa

1. Гость: `POST /api/v1/orders` с `X-Session-ID`.
2. Перейти по `paymentConfirmationUrl`.
3. Карты ([официальная дока](https://yookassa.ru/developers/payment-acceptance/testing-and-going-live/testing)):

| Сценарий | Карта (пробелы не обязательны) |
|----------|--------------------------------|
| **Успех** | `5555 5555 5555 4444` (Mastercard), `4111 1111 1111 1111` (Visa) |
| **Нет денег** | `5555 5555 5555 4600` (Mastercard), `4562 2655 8771 2390` (Visa) |

Срок — любая будущая дата, CVC и 3-D Secure — произвольно (для успеха без 3DS).

**В ЛК «История платежей» не видно новых попыток**

- Строка с **19:59** и картой **4600** — это **завершённая** попытка (`insufficient_funds`), она в списке.
- Платежи в статусе **`pending`**, где форма оборвалась **до** отправки карты в ЮKassa, часто **не попадают** в ленту «Сегодня» — их ищите по **номеру заказа** в поиске ЛК или по `paymentExternalId` из ответа `POST /orders` / `GET …/payment-status`.
- Проверка через API (test-ключ **1470173**): `GET https://api.yookassa.ru/v3/payments?created_at.gte=…` — после 19:59 MSK там есть заказы `ORD-20260920-*` (бэкенд создаёт платежи).

**На форме yoomoney.ru «технический сбой»** (до возврата на сайт):

- Если в магазине включены **Чеки от ЮKassa (54-ФЗ)**, в `POST /payments` нужен блок **`receipt`**. Включите на бэкенде: `YOOKASSA_SEND_RECEIPT=1` (по умолчанию чеки не отправляются). В ответе `POST /orders` смотрите **`paymentReceiptIncluded`**: при включённых чеках должно быть `true`; иначе в логах `YooKassa receipt skipped …`.
- **Email в форме заказа не обязателен.** Для [«Чеков от ЮKassa»](https://yookassa.ru/developers/payment-acceptance/receipts/54fz/yoomoney/basics) в API нужен email в `receipt.customer`: без почты в форме — `guest+7XXXXXXXXXX@<домен>` (`YOOKASSA_RECEIPT_FALLBACK_EMAIL_DOMAIN`, по умолчанию `noreply.dev.front-p-833.tw1.ru`). В чеке: `payment_mode=full_prepayment`, `internet=true`, `timezone=3` (MSK), телефон `7XXXXXXXXXX`. **`YOOKASSA_RECEIPT_TAX_SYSTEM_CODE`** должен совпадать с СНО в ЛK demo-магазина.
- Убедитесь, что платёж **тестовый** (`test: true` в API) и вводите **только тест-карту**, не боевую.
- Номер **без лишних пробелов** или `5555555555554444`.
- В ЛК **тест-магазина 1470173**: если включена **онлайн-касса / чеки 54-ФЗ** без тестового режима — отключите или включите «проверку чеков» для demo ([дока](https://yookassa.ru/developers/payment-acceptance/testing-and-going-live/testing)).
- **Webhook боевого магазина 1448719** не должен слать на dev URL — иначе в логах `Payment doesn't exist or access denied` (ключи test vs live). Webhook на dev только у **1470173**.

Webhook придёт с IP ЮKassa. После return_url на корзину/success **без X-Session-ID** используйте публичный `GET /api/v1/orders/{number}/payment-status` (синхронизация с ЮKassa). С сессией — `GET /api/v1/orders/{number}`.

### B. Dev-endpoints (без формы оплаты)

Включены автоматически, если `YOOKASSA_SECRET_KEY` начинается с `test_`,  
или явно: `YOOKASSA_TEST_ENDPOINTS=1` (не используйте на prod с live-ключом).

Если задан `YOOKASSA_TEST_API_TOKEN`, передавайте заголовок  
`X-Yookassa-Test-Token: <token>`.

| Метод | URL | Тело |
|-------|-----|------|
| GET | `/api/v1/payments/yookassa/test/scenarios` | — |
| POST | `/api/v1/payments/yookassa/test/succeed` | `{"orderNumber":"ORD-…"}` |
| POST | `/api/v1/payments/yookassa/test/cancel` | `{"orderNumber":"ORD-…"}` |
| POST | `/api/v1/payments/yookassa/test/insufficient-funds` | то же (как cancel + `scenario`) |

Условия: заказ в статусе `pending_payment`.  
**succeed** — как webhook успеха (корзина гостя очищается).  
**cancel / insufficient-funds** — отмена заказа, корзина остаётся.

Пример:

```bash
curl -sS -X POST 'https://dev.back-p-833.tw1.ru/api/v1/payments/yookassa/test/succeed' \
  -H 'Content-Type: application/json' \
  -d '{"orderNumber":"ORD-20260919-XXXX"}'
```

При отключённых test endpoints ответ **404** (endpoint скрыт).

## 3. Логи и проверка магазина на dev

- Файл: **`runtime/logs/yookassa.log`** на сервере (категории `yookassa.http`, `yookassa.webhook`, `yookassa.flow`).
- Каждый вызов API ЮKassa: JSON-строка **`direction: out`** (запрос) и **`direction: in`** (ответ, HTTP-код, тело). Секретный ключ **не** логируется.
- Webhook: фазы `in` / `out` / `ignored` / `error`.
- Без SSH: `GET /api/v1/payments/yookassa/test/scenarios` → **`runtimeConfig`** (`shopId`, маска ключа, `receiptTaxSystemCode`, `receiptPaymentMode`, fallback-домен email, **1470173**).

Ожидаемо на dev:

```bash
YOOKASSA_SHOP_ID=1470173
YOOKASSA_SECRET_KEY=test_…   # только test_, не live_
```

## 4. Чеклист после переключения на demo

- [ ] В `.env` только `test_` secret, не `live_`
- [ ] Webhook в настройках **демо-магазина**
- [ ] `GET .../test/scenarios` → 200 и список карт
- [ ] Гостевой заказ → оплата картой 4444 → `paymentStatus=paid`, корзина пустая
- [ ] Гостевой заказ → карта 4600 или `test/insufficient-funds` → заказ отменён, корзина на месте

## 5. Прод

На production — только **live_** ключи, **без** `YOOKASSA_TEST_ENDPOINTS`.  
Test API на prod с live secret недоступен (404).
