<?php

namespace app\services\content;

use app\helpers\SlugHelper;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\modules\admin\helpers\BlockFormPostHelper;
use app\modules\admin\helpers\HomePageCollectionsHelper;
use app\modules\admin\helpers\HomePagePartnersHelper;
use app\modules\admin\helpers\HomePageProductsHelper;

class BlockFormBuilders
{
    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function collectionsFromPost(array $post): array
    {
        $result = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageCollectionsHelper::CARD_COUNT; $i++) {
            $row = $rows[$i] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $directionId = (int)($row['catalog_direction_id'] ?? 0);
            if ($directionId <= 0) {
                continue;
            }

            $direction = CatalogDirection::find()
                ->where(['id' => $directionId])
                ->one();
            if ($direction === null) {
                continue;
            }

            $slides = self::buildHomeCollectionSlidesFromPost($direction, BlockFormPostHelper::rows($row['slides'] ?? null));
            if ($slides === []) {
                continue;
            }

            $titleUppercase = !empty($row['title_uppercase']);
            $result[] = self::buildHomeCollectionItem($direction, $slides, $titleUppercase);
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $slideRows
     * @return array<int, array<string, mixed>>
     */
    private static function buildHomeCollectionSlidesFromPost(CatalogDirection $direction, array $slideRows): array
    {
        $slides = [];
        foreach ($slideRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = BlockFormPostHelper::imageFromRow($row);
            if ($image === null) {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            if ($image['alt'] === '' && $label !== '') {
                $image['alt'] = $label;
            }

            $slides[] = [
                'label' => $label,
                'href' => self::resolveDirectionSlideHref($direction, $label),
                'image' => $image,
            ];
        }

        return array_slice($slides, 0, HomePageCollectionsHelper::SLIDES_PER_CARD);
    }

    private static function resolveDirectionSlideHref(
        CatalogDirection $direction,
        string $label
    ): string {
        $catalogBase = '/catalog/' . $direction->slug;

        if ($label === '') {
            return $catalogBase;
        }

        $subcategories = CatalogSubcategory::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($subcategories as $sub) {
            if (mb_strtolower($sub->label) === mb_strtolower($label)) {
                return $catalogBase . '/' . $sub->slug;
            }
        }

        $slug = SlugHelper::slugify($label);

        return $slug !== '' ? $catalogBase . '/' . $slug : $catalogBase;
    }

    /**
     * @param array<int, array<string, mixed>> $slides
     * @return array<string, mixed>
     */
    private static function buildHomeCollectionItem(
        CatalogDirection $direction,
        array $slides,
        bool $titleUppercase = false
    ): array {
        $item = [
            'id' => $direction->slug,
            'title' => $direction->label,
        ];

        if ($titleUppercase) {
            $item['titleUppercase'] = true;
        }

        if ($slides !== []) {
            $item['slides'] = $slides;
        }

        return $item;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function collectionsToForm(array $data): array
    {
        $cards = [];

        for ($i = 0; $i < HomePageCollectionsHelper::CARD_COUNT; $i++) {
            $item = $data[$i] ?? null;
            if (!is_array($item)) {
                $cards[] = [
                    'catalog_direction_id' => '',
                    'title_uppercase' => false,
                    'slides' => HomePageCollectionsHelper::padSlidesForForm([]),
                ];
                continue;
            }

            $direction = self::resolveHomeCollectionDirection($item);

            $slides = [];
            foreach (BlockFormPostHelper::rows($item['slides'] ?? null) as $slide) {
                if (!is_array($slide)) {
                    continue;
                }
                $slides[] = [
                    'label' => (string)($slide['label'] ?? ''),
                    'image_src' => (string)($slide['image']['src'] ?? ''),
                    'image_alt' => (string)($slide['image']['alt'] ?? ''),
                ];
            }

            $cards[] = [
                'catalog_direction_id' => $direction?->id ?? '',
                'title_uppercase' => !empty($item['titleUppercase']),
                'slides' => HomePageCollectionsHelper::padSlidesForForm($slides),
            ];
        }

        return ['cards' => $cards];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function resolveHomeCollectionDirection(array $item): ?CatalogDirection
    {
        $slug = trim((string)($item['id'] ?? ''));
        if ($slug === '') {
            return null;
        }

        $direction = CatalogDirection::find()->where(['slug' => $slug])->one();
        if ($direction !== null) {
            return $direction;
        }

        $collection = CatalogCollection::find()->where(['slug' => $slug])->one();
        if ($collection === null || $collection->direction_id === null) {
            return null;
        }

        return CatalogDirection::findOne((int)$collection->direction_id);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function homeProductsFromPost(array $post): array
    {
        $result = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageProductsHelper::CARD_COUNT; $i++) {
            $row = $rows[$i] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $productId = (int)($row['catalog_product_id'] ?? 0);
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($productId <= 0 || $image === null) {
                continue;
            }

            $product = CatalogProduct::find()
                ->where(['id' => $productId])
                ->with(['catalogModel.collection', 'catalogModel.fabricCollections', 'fabricColor.fabricCollection', 'collection'])
                ->one();
            if ($product === null) {
                continue;
            }

            $result[] = HomePageProductsHelper::buildHomeProductItemFromProduct($product, $image, $i);
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function homeProductsToForm(array $data): array
    {
        $cards = [];

        for ($i = 0; $i < HomePageProductsHelper::CARD_COUNT; $i++) {
            $item = $data[$i] ?? null;
            if (!is_array($item)) {
                $cards[] = self::emptyHomeProductCard();
                continue;
            }

            $productId = HomePageProductsHelper::resolveProductIdFromApiItem($item);
            $productTitle = '';
            if ($productId !== null) {
                $pickerItem = HomePageProductsHelper::pickerItemByProductId($productId);
                $productTitle = $pickerItem['productTitle'] ?? ($pickerItem['title'] ?? '');
            }

            $cards[] = [
                'catalog_product_id' => $productId ?? '',
                'product_search' => $productTitle !== '' ? $productTitle : ($item['name'] ?? ''),
                'image_src' => $item['image']['src'] ?? '',
                'image_alt' => $item['image']['alt'] ?? '',
            ];
        }

        return ['cards' => $cards];
    }

    /**
     * @return array<string, string>
     */
    private static function emptyHomeProductCard(): array
    {
        return [
            'catalog_product_id' => '',
            'product_search' => '',
            'image_src' => '',
            'image_alt' => '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function homePartnersFromPost(array $post): array
    {
        $cards = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePagePartnersHelper::CARD_COUNT; $i++) {
            $row = $rows[$i] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' || $image === null) {
                continue;
            }

            $card = array_filter([
                'title' => $title,
                'href' => trim((string)($row['href'] ?? '')),
                'text' => trim((string)($row['text'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            $card['image'] = $image;

            $cards[] = $card;
        }

        return array_filter([
            'intro' => trim((string)($post['intro'] ?? '')),
            'cards' => $cards,
        ], static fn ($v): bool => $v !== '' && $v !== []);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function homePartnersToForm(array $data): array
    {
        $sourceCards = BlockFormPostHelper::rows($data['cards'] ?? null);
        $cards = [];

        for ($i = 0; $i < HomePagePartnersHelper::CARD_COUNT; $i++) {
            $row = $sourceCards[$i] ?? null;
            if (!is_array($row)) {
                $cards[] = [
                    'title' => '',
                    'href' => '',
                    'text' => '',
                    'image_src' => '',
                    'image_alt' => '',
                ];
                continue;
            }

            $cards[] = [
                'title' => $row['title'] ?? '',
                'href' => $row['href'] ?? '',
                'text' => $row['text'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return [
            'intro' => $data['intro'] ?? '',
            'cards' => $cards,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function journalCardsFromPost(array $post, string $listKey = 'items'): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post[$listKey] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'number' => trim((string)($row['number'] ?? '')),
                'title' => $title,
                'date' => trim((string)($row['date'] ?? '')),
                'excerpt' => trim((string)($row['excerpt'] ?? '')),
                'to' => trim((string)($row['to'] ?? '')),
                'imagePosition' => trim((string)($row['image_position'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $item['image'] = $image;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function journalCardsToForm(array $data, string $listKey = 'items'): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'title' => $row['title'] ?? '',
                'date' => $row['date'] ?? '',
                'excerpt' => $row['excerpt'] ?? '',
                'to' => $row['to'] ?? '',
                'image_position' => $row['imagePosition'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return [$listKey => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function missionFromPost(array $post): array
    {
        $items = self::imageCardsFromPost(BlockFormPostHelper::rows($post['items'] ?? null));

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function missionToForm(array $data): array
    {
        return ['items' => self::imageCardsToForm(BlockFormPostHelper::rows($data['items'] ?? null))];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function imageCardsFromPost(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ($row['description'] ?? '')));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $text === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');

            if (isset($row['description']) && trim((string)$row['description']) !== '') {
                $item['description'] = trim((string)$row['description']);
                unset($item['text']);
            }

            if ($image !== null) {
                $item['image'] = $image;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function imageCardsToForm(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? ($row['description'] ?? ''),
                'description' => $row['description'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function formatsFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'id' => trim((string)($row['id'] ?? '')),
                'title' => $title,
                'text' => trim((string)($row['text'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $item['image'] = $image;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function formatsToForm(array $data): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'id' => $row['id'] ?? '',
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function formatsSectionFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['formats_title'] ?? '')),
            'subtitle' => trim((string)($post['formats_subtitle'] ?? '')),
            'lead' => trim((string)($post['formats_lead'] ?? 'Вы получите:')),
        ], static fn (string $v): bool => $v !== '');

        $src = trim((string)($post['formats_image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['formats_image_alt'] ?? '')),
            ];
        }

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $text = trim((string)($row['text'] ?? ''));
            $number = trim((string)($row['number'] ?? ''));
            if ($text === '') {
                continue;
            }

            $item = ['text' => $text];
            if ($number !== '') {
                $item['number'] = $number;
            }

            $items[] = $item;
        }

        $result['items'] = $items;

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function formatsSectionToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        return [
            'formats_title' => $data['title'] ?? '',
            'formats_subtitle' => $data['subtitle'] ?? '',
            'formats_lead' => $data['lead'] ?? '',
            'formats_image_src' => $data['image']['src'] ?? '',
            'formats_image_alt' => $data['image']['alt'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function audienceFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $result[] = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function audienceToForm(array $data): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function materialsSectionFromPost(array $post): array
    {
        $result = array_filter([
            'text' => trim((string)($post['materials_text'] ?? '')),
            'archiveLabel' => trim((string)($post['archive_label'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $archiveUrl = trim((string)($post['archive_url'] ?? ''));
        if ($archiveUrl !== '') {
            $result['archiveUrl'] = $archiveUrl;
        }

        $src = trim((string)($post['materials_image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['materials_image_alt'] ?? '')),
            ];
        }

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $items[] = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');
        }

        $result['items'] = $items;

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function materialsSectionToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        return [
            'materials_text' => $data['text'] ?? '',
            'archive_url' => $data['archiveUrl'] ?? '',
            'archive_label' => $data['archiveLabel'] ?? '',
            'materials_image_src' => $data['image']['src'] ?? '',
            'materials_image_alt' => $data['image']['alt'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function audienceSectionFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['audience_title'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $src = trim((string)($post['audience_image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['audience_image_alt'] ?? '')),
            ];
        }

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            $number = trim((string)($row['number'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $item = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');

            if ($number !== '') {
                $item['number'] = $number;
            }

            $items[] = $item;
        }

        $result['items'] = $items;

        $stats = [];
        foreach (BlockFormPostHelper::rows($post['stats'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $text = trim((string)($row['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $stats[] = ['text' => $text];
        }

        if ($stats !== []) {
            $result['stats'] = $stats;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function audienceSectionToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        $stats = [];
        foreach (BlockFormPostHelper::rows($data['stats'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $stats[] = [
                'text' => $row['text'] ?? '',
            ];
        }

        return [
            'audience_title' => $data['title'] ?? '',
            'audience_image_src' => $data['image']['src'] ?? '',
            'audience_image_alt' => $data['image']['alt'] ?? '',
            'items' => $items,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function salonFormatsSectionFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['salon_formats_title'] ?? '')),
            'subtitle' => trim((string)($post['salon_formats_subtitle'] ?? '')),
            'conditionsTitle' => trim((string)($post['salon_conditions_title'] ?? 'Общие условия партнёрства:')),
        ], static fn (string $v): bool => $v !== '');

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $item = array_filter([
                'id' => trim((string)($row['id'] ?? '')),
                'title' => $title,
                'text' => trim((string)($row['text'] ?? '')),
                'area' => trim((string)($row['area'] ?? '')),
                'assortment' => trim((string)($row['assortment'] ?? '')),
                'profit' => trim((string)($row['profit'] ?? '')),
                'employees' => trim((string)($row['employees'] ?? '')),
                'investment' => trim((string)($row['investment'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            $items[] = $item;
        }

        $result['items'] = $items;

        $conditions = [];
        foreach (BlockFormPostHelper::rows($post['conditions'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            $value = trim((string)($row['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }

            $conditions[] = array_filter([
                'label' => $label,
                'value' => $value,
            ], static fn (string $v): bool => $v !== '');
        }

        if ($conditions !== []) {
            $result['conditions'] = $conditions;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function salonFormatsSectionToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'id' => $row['id'] ?? '',
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
                'area' => $row['area'] ?? '',
                'assortment' => $row['assortment'] ?? '',
                'profit' => $row['profit'] ?? '',
                'employees' => $row['employees'] ?? '',
                'investment' => $row['investment'] ?? '',
            ];
        }

        $conditions = [];
        foreach (BlockFormPostHelper::rows($data['conditions'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $conditions[] = [
                'label' => $row['label'] ?? '',
                'value' => $row['value'] ?? '',
            ];
        }

        return [
            'salon_formats_title' => $data['title'] ?? '',
            'salon_formats_subtitle' => $data['subtitle'] ?? '',
            'salon_conditions_title' => $data['conditionsTitle'] ?? 'Общие условия партнёрства:',
            'items' => $items,
            'conditions' => $conditions,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function gallerySimpleFromPost(array $post): array
    {
        $slides = [];
        foreach (BlockFormPostHelper::rows($post['slides'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = BlockFormPostHelper::imageFromRow($row);
            if ($image === null) {
                continue;
            }

            $slides[] = ['image' => $image];
        }

        return ['slides' => $slides];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function gallerySimpleToForm(array $data): array
    {
        $slides = [];
        foreach (BlockFormPostHelper::rows($data['slides'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $slides[] = [
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        if ($slides === []) {
            $slides = [['image_src' => '', 'image_alt' => '']];
        }

        return ['slides' => $slides];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function vacanciesValuesFromPost(array $post): array
    {
        $result = self::aboutIntroFromPost($post);
        $title = trim((string)($post['values_title'] ?? ''));
        if ($title !== '') {
            $result['title'] = $title;
        }

        $gallery = self::gallerySimpleFromPost($post);
        if ($gallery['slides'] !== []) {
            $result['slides'] = $gallery['slides'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function vacanciesValuesToForm(array $data): array
    {
        $form = self::aboutIntroToForm($data);
        $form['values_title'] = $data['title'] ?? '';
        $form = array_merge($form, self::gallerySimpleToForm($data));

        return $form;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function vacanciesGalleryFromPost(array $post): array
    {
        return self::gallerySimpleFromPost($post);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function vacanciesGalleryToForm(array $data): array
    {
        return self::gallerySimpleToForm($data);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function aboutIntroFromPost(array $post): array
    {
        $paragraphs = [];
        foreach (BlockFormPostHelper::rows($post['paragraphs'] ?? null) as $row) {
            $text = trim((string)(is_array($row) ? ($row['text'] ?? '') : $row));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        $result = [];
        if ($paragraphs !== []) {
            $result['paragraphs'] = $paragraphs;
        }

        $image = BlockFormPostHelper::imageFromRow([
            'image_src' => $post['intro_image_src'] ?? '',
            'image_alt' => $post['intro_image_alt'] ?? '',
        ]);
        if ($image !== null) {
            $result['image'] = $image;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function aboutIntroToForm(array $data): array
    {
        $paragraphs = [];
        foreach (BlockFormPostHelper::rows($data['paragraphs'] ?? null) as $paragraph) {
            $text = trim(is_string($paragraph) ? $paragraph : (string)($paragraph['text'] ?? ''));
            if ($text !== '') {
                $paragraphs[] = ['text' => $text];
            }
        }
        if ($paragraphs === []) {
            $paragraphs = [['text' => '']];
        }

        $image = is_array($data['image'] ?? null) ? $data['image'] : [];

        return [
            'paragraphs' => $paragraphs,
            'intro_image_src' => $image['src'] ?? '',
            'intro_image_alt' => $image['alt'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function aboutGalleryFromPost(array $post): array
    {
        $cards = [];
        foreach (BlockFormPostHelper::rows($post['cards'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = BlockFormPostHelper::imageFromRow($row);
            if ($image === null) {
                continue;
            }

            $cards[] = ['image' => $image];
        }

        return array_filter([
            'title' => trim((string)($post['gallery_title'] ?? '')),
            'cards' => $cards,
        ], static fn (mixed $value): bool => $value !== '' && $value !== []);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function aboutGalleryToForm(array $data): array
    {
        $cards = [];
        foreach (BlockFormPostHelper::rows($data['cards'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = is_array($row['image'] ?? null) ? $row['image'] : [];
            $cards[] = [
                'image_src' => $image['src'] ?? '',
                'image_alt' => $image['alt'] ?? '',
            ];
        }
        if ($cards === []) {
            $cards = [['image_src' => '', 'image_alt' => '']];
        }

        return [
            'gallery_title' => $data['title'] ?? '',
            'cards' => $cards,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function aboutTimelineFromPost(array $post): array
    {
        $stages = [];
        foreach (BlockFormPostHelper::rows($post['stages'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $year = trim((string)($row['year'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));

            $images = [];
            foreach (BlockFormPostHelper::rows($row['images'] ?? null) as $imageRow) {
                if (!is_array($imageRow)) {
                    continue;
                }

                $image = BlockFormPostHelper::imageFromRow($imageRow);
                if ($image !== null) {
                    $images[] = $image;
                }
            }

            if ($year === '' && $title === '' && $text === '' && $images === []) {
                continue;
            }

            $stage = array_filter([
                'year' => $year,
                'label' => $year !== '' ? $year : trim((string)($row['label'] ?? '')),
                'title' => $title,
                'text' => $text,
            ], static fn (string $value): bool => $value !== '');

            if ($images !== []) {
                $stage['images'] = $images;
                $stage['image'] = $images[0];
            }

            $stages[] = $stage;
        }

        return $stages !== [] ? ['stages' => $stages] : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function aboutTimelineToForm(array $data): array
    {
        $stages = [];
        foreach (BlockFormPostHelper::rows($data['stages'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $images = [];
            foreach (self::aboutTimelineStageImages($row) as $image) {
                $images[] = [
                    'image_src' => $image['src'] ?? '',
                    'image_alt' => $image['alt'] ?? '',
                ];
            }
            if ($images === []) {
                $images = [['image_src' => '', 'image_alt' => '']];
            }

            $stages[] = [
                'year' => $row['year'] ?? ($row['label'] ?? ''),
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
                'images' => $images,
            ];
        }

        if ($stages === []) {
            $stages = [['year' => '', 'title' => '', 'text' => '', 'images' => [['image_src' => '', 'image_alt' => '']]]];
        }

        return ['stages' => $stages];
    }

    /**
     * @param array<string, mixed> $stage
     * @return array<int, array{src?: string, alt?: string}>
     */
    private static function aboutTimelineStageImages(array $stage): array
    {
        if (isset($stage['images']) && is_array($stage['images'])) {
            $images = [];
            foreach ($stage['images'] as $image) {
                if (!is_array($image)) {
                    continue;
                }

                $src = trim((string)($image['src'] ?? ''));
                if ($src !== '') {
                    $images[] = $image;
                }
            }

            if ($images !== []) {
                return $images;
            }
        }

        if (isset($stage['image']) && is_array($stage['image'])) {
            $src = trim((string)($stage['image']['src'] ?? ''));
            if ($src !== '') {
                return [$stage['image']];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function galleryCaptionedFromPost(array $post): array
    {
        $slides = [];
        foreach (BlockFormPostHelper::rows($post['slides'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $image === null) {
                continue;
            }

            $slide = array_filter([
                'title' => $title,
                'subtitle' => trim((string)($row['subtitle'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $slide['image'] = $image;
            }

            $slides[] = $slide;
        }

        return ['slides' => $slides];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function galleryCaptionedToForm(array $data): array
    {
        $slides = [];
        foreach (BlockFormPostHelper::rows($data['slides'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $slides[] = [
                'title' => $row['title'] ?? '',
                'subtitle' => $row['subtitle'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return ['slides' => $slides];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function galleryStackSectionFromPost(array $post): array
    {
        $result = array_filter([
            'text' => trim((string)($post['gallery_text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $photos = [];
        foreach (BlockFormPostHelper::rows($post['photos'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = BlockFormPostHelper::imageFromRow($row);
            if ($image === null) {
                continue;
            }

            $item = ['image' => $image];

            $rotate = trim((string)($row['rotate'] ?? ''));
            if ($rotate !== '' && is_numeric($rotate)) {
                $item['rotate'] = (float)$rotate;
            }

            $offsetX = trim((string)($row['offset_x'] ?? ''));
            if ($offsetX !== '' && is_numeric($offsetX)) {
                $item['offsetX'] = (float)$offsetX;
            }

            $offsetY = trim((string)($row['offset_y'] ?? ''));
            if ($offsetY !== '' && is_numeric($offsetY)) {
                $item['offsetY'] = (float)$offsetY;
            }

            $photos[] = $item;
        }

        $result['photos'] = $photos;

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function galleryStackSectionToForm(array $data): array
    {
        $photos = [];
        foreach (BlockFormPostHelper::rows($data['photos'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $photos[] = [
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
                'rotate' => $row['rotate'] ?? '',
                'offset_x' => $row['offsetX'] ?? '',
                'offset_y' => $row['offsetY'] ?? '',
            ];
        }

        return [
            'gallery_text' => $data['text'] ?? '',
            'photos' => $photos,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function termsFromPost(array $post): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $items[] = array_filter([
                'title' => $title,
                'text' => $text,
            ], static fn (string $v): bool => $v !== '');
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function termsToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function presentationFromPost(array $post): array
    {
        return array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'fileUrl' => trim((string)($post['file_url'] ?? '')),
            'fileLabel' => trim((string)($post['file_label'] ?? '')),
        ], static fn (string $v): bool => $v !== '');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function presentationToForm(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'file_url' => $data['fileUrl'] ?? '',
            'file_label' => $data['fileLabel'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function presentationSectionFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['presentation_title'] ?? '')),
            'fileUrl' => trim((string)($post['file_url'] ?? '')),
            'fileLabel' => trim((string)($post['file_label'] ?? '')),
            'fileHint' => trim((string)($post['file_hint'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $src = trim((string)($post['presentation_image_src'] ?? ''));
        if ($src !== '') {
            $result['image'] = [
                'src' => $src,
                'alt' => trim((string)($post['presentation_image_alt'] ?? '')),
            ];
        }

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $text = trim((string)($row['text'] ?? ''));
            $number = trim((string)($row['number'] ?? ''));
            if ($text === '') {
                continue;
            }

            $item = ['text' => $text];
            if ($number !== '') {
                $item['number'] = $number;
            }

            $items[] = $item;
        }

        if ($items !== []) {
            $result['items'] = $items;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function presentationSectionToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'text' => $row['text'] ?? '',
            ];
        }

        return [
            'presentation_title' => $data['title'] ?? '',
            'file_url' => $data['fileUrl'] ?? '',
            'file_label' => $data['fileLabel'] ?? '',
            'file_hint' => $data['fileHint'] ?? '',
            'presentation_image_src' => $data['image']['src'] ?? '',
            'presentation_image_alt' => $data['image']['alt'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function samplesFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'text' => trim((string)($post['text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $image = BlockFormPostHelper::imageFromRow($post);
        if ($image !== null) {
            $result['image'] = $image;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function samplesToForm(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'text' => $data['text'] ?? '',
            'image_src' => $data['image']['src'] ?? '',
            'image_alt' => $data['image']['alt'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function photoStackFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $image = BlockFormPostHelper::imageFromRow($row);
            if ($image === null) {
                continue;
            }

            $item = ['image' => $image];

            $rotate = trim((string)($row['rotate'] ?? ''));
            if ($rotate !== '' && is_numeric($rotate)) {
                $item['rotate'] = (float)$rotate;
            }

            $offsetX = trim((string)($row['offset_x'] ?? ''));
            if ($offsetX !== '' && is_numeric($offsetX)) {
                $item['offsetX'] = (float)$offsetX;
            }

            $offsetY = trim((string)($row['offset_y'] ?? ''));
            if ($offsetY !== '' && is_numeric($offsetY)) {
                $item['offsetY'] = (float)$offsetY;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function photoStackToForm(array $data): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
                'rotate' => $row['rotate'] ?? '',
                'offset_x' => $row['offsetX'] ?? '',
                'offset_y' => $row['offsetY'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function regionsFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['regions'] ?? null) as $regionRow) {
            if (!is_array($regionRow)) {
                continue;
            }

            $name = trim((string)($regionRow['name'] ?? ''));
            $id = trim((string)($regionRow['id'] ?? ''));
            if ($name === '' && $id === '') {
                continue;
            }

            $stores = [];
            foreach (BlockFormPostHelper::rows($regionRow['stores'] ?? null) as $storeRow) {
                if (!is_array($storeRow)) {
                    continue;
                }

                $storeName = trim((string)($storeRow['name'] ?? ''));
                $address = trim((string)($storeRow['address'] ?? ''));
                if ($storeName === '' && $address === '') {
                    continue;
                }

                $lon = trim((string)($storeRow['lon'] ?? ''));
                $lat = trim((string)($storeRow['lat'] ?? ''));
                $coordinates = null;
                if ($lon !== '' && $lat !== '' && is_numeric($lon) && is_numeric($lat)) {
                    $coordinates = [(float)$lon, (float)$lat];
                }

                $store = array_filter([
                    'id' => trim((string)($storeRow['id'] ?? '')),
                    'name' => $storeName,
                    'address' => $address,
                    'phone' => trim((string)($storeRow['phone'] ?? '')),
                    'hours' => trim((string)($storeRow['hours'] ?? '')),
                ], static fn (string $v): bool => $v !== '');

                if ($coordinates !== null) {
                    $store['coordinates'] = $coordinates;
                }

                $stores[] = $store;
            }

            $region = array_filter([
                'id' => $id,
                'name' => $name,
            ], static fn (string $v): bool => $v !== '');

            if (BlockFormPostHelper::boolValue($regionRow['default_open'] ?? false)) {
                $region['defaultOpen'] = true;
            }

            $pointsCount = trim((string)($regionRow['points_count'] ?? ''));
            if ($pointsCount !== '' && is_numeric($pointsCount)) {
                $region['pointsCount'] = (int)$pointsCount;
            } elseif ($stores !== []) {
                $region['pointsCount'] = count($stores);
            }

            if ($stores !== []) {
                $region['stores'] = $stores;
            }

            $result[] = $region;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function regionsToForm(array $data): array
    {
        $regions = [];
        foreach ($data as $region) {
            if (!is_array($region)) {
                continue;
            }

            $stores = [];
            foreach (BlockFormPostHelper::rows($region['stores'] ?? null) as $store) {
                if (!is_array($store)) {
                    continue;
                }

                $coords = $store['coordinates'] ?? null;
                $lon = '';
                $lat = '';
                if (is_array($coords) && count($coords) >= 2) {
                    $lon = $coords[0];
                    $lat = $coords[1];
                }

                $stores[] = [
                    'id' => $store['id'] ?? '',
                    'name' => $store['name'] ?? '',
                    'address' => $store['address'] ?? '',
                    'lon' => $lon,
                    'lat' => $lat,
                    'phone' => $store['phone'] ?? '',
                    'hours' => $store['hours'] ?? '',
                ];
            }

            $regions[] = [
                'id' => $region['id'] ?? '',
                'name' => $region['name'] ?? '',
                'points_count' => $region['pointsCount'] ?? '',
                'default_open' => !empty($region['defaultOpen']),
                'stores' => $stores,
            ];
        }

        return ['regions' => $regions];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function faqCategoriesFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['categories'] ?? null) as $catRow) {
            if (!is_array($catRow)) {
                continue;
            }

            $label = trim((string)($catRow['label'] ?? ''));
            $id = trim((string)($catRow['id'] ?? ''));
            if ($label === '' && $id === '') {
                continue;
            }

            $items = [];
            foreach (BlockFormPostHelper::rows($catRow['items'] ?? null) as $itemRow) {
                if (!is_array($itemRow)) {
                    continue;
                }

                $question = trim((string)($itemRow['question'] ?? ''));
                if ($question === '') {
                    continue;
                }

                $paragraphs = self::faqAnswerFromItemPost($itemRow);
                $item = ['question' => $question];

                if ($paragraphs !== []) {
                    $item['paragraphs'] = $paragraphs;
                }

                $items[] = $item;
            }

            $category = array_filter([
                'id' => $id,
                'label' => $label,
                'icon' => trim((string)($catRow['icon'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($items !== []) {
                $category['items'] = $items;
            }

            $result[] = $category;
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $paragraphs
     */
    public static function faqAnswerToForm(array $paragraphs): string
    {
        $blocks = [];
        foreach ($paragraphs as $paragraph) {
            if (is_string($paragraph)) {
                $text = trim($paragraph);
                if ($text !== '') {
                    $blocks[] = $text;
                }
                continue;
            }

            if (!is_array($paragraph)) {
                continue;
            }

            $line = '';
            foreach ($paragraph as $part) {
                if (!is_array($part)) {
                    continue;
                }

                $text = (string)($part['t'] ?? '');
                $link = trim((string)($part['to'] ?? ''));
                if ($link !== '' && ($part['s'] ?? '') === 'link') {
                    $line .= '[' . $text . '](' . $link . ')';
                    continue;
                }

                $line .= $text;
            }

            $line = trim($line);
            if ($line !== '') {
                $blocks[] = $line;
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param array<string, mixed> $itemRow
     * @return array<int, mixed>
     */
    public static function faqAnswerFromItemPost(array $itemRow): array
    {
        $answer = trim((string)($itemRow['answer'] ?? ''));
        if ($answer !== '') {
            return self::faqAnswerMarkdownToParagraphs($answer);
        }

        return self::faqParagraphsFromPost(BlockFormPostHelper::rows($itemRow['paragraphs'] ?? null));
    }

    /**
     * @return array<int, mixed>
     */
    public static function faqAnswerMarkdownToParagraphs(string $answer): array
    {
        $paragraphs = [];
        foreach (preg_split('/\R\s*\R/', $answer) ?: [] as $block) {
            $block = trim((string)$block);
            if ($block === '') {
                continue;
            }

            if (!preg_match('/\[([^\]]+)\]\(([^)]+)\)/', $block)) {
                $paragraphs[] = $block;
                continue;
            }

            $parts = self::faqParseMarkdownLinks($block);
            if ($parts !== []) {
                $paragraphs[] = $parts;
            }
        }

        return $paragraphs;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function faqParseMarkdownLinks(string $text): array
    {
        $parts = [];
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        $offset = 0;

        while (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $matchStart = (int)$matches[0][1];
            if ($matchStart > $offset) {
                $plain = substr($text, $offset, $matchStart - $offset);
                if ($plain !== '') {
                    $parts[] = ['t' => $plain];
                }
            }

            $parts[] = [
                't' => (string)$matches[1][0],
                'to' => trim((string)$matches[2][0]),
                's' => 'link',
            ];

            $offset = $matchStart + strlen((string)$matches[0][0]);
        }

        if ($offset < strlen($text)) {
            $tail = substr($text, $offset);
            if ($tail !== '') {
                $parts[] = ['t' => $tail];
            }
        }

        return $parts;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, mixed>
     */
    private static function faqParagraphsFromPost(array $rows): array
    {
        $paragraphs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = (string)($row['type'] ?? 'text');
            if ($type === 'rich') {
                $parts = [];
                foreach (BlockFormPostHelper::rows($row['parts'] ?? null) as $partRow) {
                    if (!is_array($partRow)) {
                        continue;
                    }

                    $text = trim((string)($partRow['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }

                    $part = ['t' => $text];
                    $link = trim((string)($partRow['link_to'] ?? ''));
                    $style = trim((string)($partRow['style'] ?? ''));

                    if ($link !== '') {
                        $part['to'] = $link;
                        $part['s'] = 'link';
                    } elseif ($style === 'brand') {
                        $part['s'] = 'brand';
                    }

                    $parts[] = $part;
                }

                if ($parts !== []) {
                    $paragraphs[] = $parts;
                }
            } else {
                $text = trim((string)($row['text'] ?? ''));
                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            }
        }

        return $paragraphs;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function faqCategoriesToForm(array $data): array
    {
        $categories = [];
        foreach ($data as $cat) {
            if (!is_array($cat)) {
                continue;
            }

            $items = [];
            foreach (BlockFormPostHelper::rows($cat['items'] ?? null) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $items[] = [
                    'question' => $item['question'] ?? '',
                    'answer' => self::faqAnswerToForm(BlockFormPostHelper::rows($item['paragraphs'] ?? null)),
                ];
            }

            $categories[] = [
                'id' => $cat['id'] ?? '',
                'label' => $cat['label'] ?? '',
                'icon' => $cat['icon'] ?? '',
                'items' => $items,
            ];
        }

        return ['categories' => $categories];
    }

    /**
     * Вкладки журнала: обновляет названия, сохраняет существующие статьи.
     *
     * @param array<string, mixed> $post
     * @param array<int, array<string, mixed>> $existingCategories
     * @return array<int, array<string, mixed>>
     */
    public static function journalCategoryTabsFromPost(array $post, array $existingCategories): array
    {
        $existingById = [];
        foreach ($existingCategories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $id = trim((string)($cat['id'] ?? ''));
            if ($id !== '') {
                $existingById[$id] = $cat;
            }
        }

        $result = [];
        foreach (BlockFormPostHelper::rows($post['categories'] ?? null) as $catRow) {
            if (!is_array($catRow)) {
                continue;
            }

            $label = trim((string)($catRow['label'] ?? ''));
            $id = trim((string)($catRow['id'] ?? ''));
            if ($label === '' && $id === '') {
                continue;
            }

            $existing = $existingById[$id] ?? [];
            $icon = trim((string)($catRow['icon'] ?? ''));
            if ($icon === '' && is_array($existing)) {
                $icon = trim((string)($existing['icon'] ?? ''));
            }

            $category = array_filter([
                'id' => $id,
                'label' => $label,
                'icon' => $icon,
            ], static fn (string $v): bool => $v !== '');

            $category['items'] = [];
            $result[] = $category;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function journalCategoriesTabsToForm(array $data): array
    {
        $categories = [];
        foreach ($data as $cat) {
            if (!is_array($cat)) {
                continue;
            }

            $categories[] = [
                'id' => $cat['id'] ?? '',
                'label' => $cat['label'] ?? '',
                'icon' => $cat['icon'] ?? '',
            ];
        }

        return ['categories' => $categories];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function comfortFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'number' => trim((string)($row['number'] ?? '')),
                'title' => $title,
                'description' => trim((string)($row['description'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $item['image'] = $image;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function comfortToForm(array $data): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function stackSectionFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['stack_intro_title'] ?? '')),
            'text' => trim((string)($post['stack_intro_text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $items = self::stackItemsFromPost($post);
        if ($items !== []) {
            $result['items'] = $items;
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function stackSectionToForm(array $data): array
    {
        if (array_is_list($data)) {
            $data = ['items' => $data];
        }

        return [
            'stack_intro_title' => $data['title'] ?? '',
            'stack_intro_text' => $data['text'] ?? '',
            'items' => self::stackItemsToForm($data['items'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function stackItemsFromPost(array $post): array
    {
        $result = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($title === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'number' => trim((string)($row['number'] ?? '')),
                'title' => $title,
                'text' => trim((string)($row['text'] ?? '')),
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $item['image'] = $image;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<int, array<string, mixed>>
     */
    public static function stackItemsToForm(array $data): array
    {
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'number' => $row['number'] ?? '',
                'title' => $row['title'] ?? '',
                'text' => $row['text'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     * @deprecated use stackSectionFromPost
     */
    public static function stackFromPost(array $post): array
    {
        return self::stackItemsFromPost($post);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<string, mixed>
     * @deprecated use stackSectionToForm
     */
    public static function stackToForm(array $data): array
    {
        return ['items' => self::stackItemsToForm($data)];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function ethicsFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'text' => trim((string)($post['text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $image = BlockFormPostHelper::imageFromRow($post);
        if ($image !== null) {
            $result['image'] = $image;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function ethicsToForm(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'text' => $data['text'] ?? '',
            'image_src' => $data['image']['src'] ?? '',
            'image_alt' => $data['image']['alt'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function libraryImplementedModelsFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'description' => trim((string)($post['description'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $author = trim((string)($row['author'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            $projectName = trim((string)($row['project_name'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);

            if ($author === '' && $title === '' && $projectName === '' && $image === null) {
                continue;
            }

            $item = array_filter([
                'author' => $author,
                'title' => $title,
                'projectName' => $projectName,
            ], static fn (string $v): bool => $v !== '');

            if ($image !== null) {
                $item['image'] = $image;
            }

            $items[] = $item;
        }

        if ($items !== []) {
            $result['items'] = $items;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function libraryImplementedModelsToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'author' => $row['author'] ?? '',
                'title' => $row['title'] ?? '',
                'project_name' => $row['projectName'] ?? '',
                'image_src' => $row['image']['src'] ?? '',
                'image_alt' => $row['image']['alt'] ?? '',
            ];
        }

        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function libraryYourIdeaFromPost(array $post): array
    {
        $result = array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'text' => trim((string)($post['text'] ?? '')),
            'textSecondary' => trim((string)($post['text_secondary'] ?? '')),
            'ctaLabel' => trim((string)($post['cta_label'] ?? '')),
            'ctaHref' => trim((string)($post['cta_href'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $image = BlockFormPostHelper::imageFromRow($post);
        if ($image !== null) {
            $result['image'] = $image;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function libraryYourIdeaToForm(array $data): array
    {
        $image = is_array($data['image'] ?? null) ? $data['image'] : [];

        return [
            'title' => $data['title'] ?? '',
            'text' => $data['text'] ?? '',
            'text_secondary' => $data['textSecondary'] ?? '',
            'cta_label' => $data['ctaLabel'] ?? '',
            'cta_href' => $data['ctaHref'] ?? '',
            'image_src' => $image['src'] ?? '',
            'image_alt' => $image['alt'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function libraryDocumentsFromPost(array $post): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $subtitle = trim((string)($row['subtitle'] ?? ''));
            $fileUrl = trim((string)($row['file_url'] ?? ''));

            if ($title === '' && $subtitle === '' && $fileUrl === '') {
                continue;
            }

            $item = array_filter([
                'title' => $title,
                'subtitle' => $subtitle,
                'fileUrl' => $fileUrl,
            ], static fn (string $v): bool => $v !== '');

            $items[] = $item;
        }

        if ($items === []) {
            return [];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function libraryDocumentsToForm(array $data): array
    {
        $items = [];
        foreach (BlockFormPostHelper::rows($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'title' => $row['title'] ?? '',
                'subtitle' => $row['subtitle'] ?? '',
                'file_url' => $row['fileUrl'] ?? '',
            ];
        }

        return ['items' => $items];
    }

    public static function titleTextFromPost(array $post): array
    {
        return array_filter([
            'title' => trim((string)($post['title'] ?? '')),
            'text' => trim((string)($post['text'] ?? '')),
        ], static fn (string $v): bool => $v !== '');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function titleTextToForm(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'text' => $data['text'] ?? '',
        ];
    }
}
