# Creative: Раздел «Настройки» каталога

## Согласованные решения (2026-08-05)

1. **Габариты** — 5 полей как на сайте; в товаре: выбор шаблона из стандарта или ручной ввод.
2. **Коллекция на товаре** — поле всегда в форме, необязательное (nullable FK).
3. **Доступ к «Настройкам»** — только `admin`.
4. **Старые URL** (`/admin/catalog-group/*`, `/admin/catalog-collection/*`) — удалить, без редиректов.

## Габариты — поля

| Ключ | Подпись | Пример |
|------|---------|--------|
| overall_size | Размер (Ш×В×Г) | 2450×820×1050 мм |
| seat_depth | Глубина посадочного места | 640 мм |
| seat_height | Высота посадочного места | 440 мм |
| armrest_width | Ширина подлокотника | 250 мм |
| clearance | Клиренс | 120 мм |

## Модель

- `catalog_dimension_templates` — справочник стандартов (5 полей + name, slug, sort_order, is_active).
- `catalog_products` — 5 полей габаритов + nullable `dimension_template_id` (для подсказки «из шаблона»).
- UI товара: dropdown «Стандарт» → автозаполнение; любое изменение поля = свои значения.

## Раскладка

Визуальная карточка товара (featured / stacked / compact), справочник `catalog_layouts`.

## Бейдж

Справочник `catalog_badges` с image_id; на товаре только dropdown.

## Коллекция

- `catalog_collections.group_id` FK + текстовое `name`.
- Убрать `collection_label` с товара; API берёт `collection.name`.
