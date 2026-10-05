<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\modules\admin\helpers\BlockFormPostHelper;
use app\modules\admin\helpers\HomePageCollectionsHelper;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\content\BlockFormBuilders;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageHomeHelper
{
    /**
     * @return string[]
     */
    public static function tabKeys(): array
    {
        return ['hero', 'collections', 'products', 'partners'];
    }

    /**
     * @return array<string, string>
     */
    public static function tabLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'collections' => 'Коллекции',
            'products' => 'Товары',
            'partners' => 'Партнёры',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tabLeads(): array
    {
        return [
            'hero' => 'SEO, фото баннера и тексты на главной — в одной форме.',
            'collections' => 'Философия бренда и карточки направлений с фото.',
            'products' => 'Карточки товаров на главной.',
            'partners' => 'Вводный текст и две карточки с фото.',
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'home';
    }

    public static function shouldRedirectToHomeEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'home';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'home') {
            return 'hero';
        }

        return match ($block->block_key) {
            'seo' => 'hero',
            'philosophy' => 'collections',
            'journal' => 'hero',
            default => in_array($block->block_key, self::tabKeys(), true) ? $block->block_key : 'hero',
        };
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::tabKeys(), true) ? $tab : 'hero';
    }

    /**
     * @return array<string, ContentBlock>
     */
    public static function indexBlocksByKey(int $pageId): array
    {
        $blocks = ContentBlock::find()
            ->where(['page_id' => $pageId])
            ->all();

        $map = [];
        foreach ($blocks as $block) {
            $map[$block->block_key] = $block;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildFormDataForTab(BlockFormHandler $handler, int $pageId, string $tab): array
    {
        $tab = self::normalizeTab($tab);
        $blocks = self::indexBlocksByKey($pageId);

        return match ($tab) {
            'hero' => self::buildHeroTabFormData($handler, $blocks),
            'collections' => self::buildCollectionsTabFormData($handler, $blocks),
            'products' => self::buildProductsTabFormData($handler, $blocks),
            'partners' => self::buildPartnersTabFormData($handler, $blocks),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDataFromPost(string $tab, array $post): array
    {
        $tab = self::normalizeTab($tab);

        return match ($tab) {
            'hero' => self::heroFormDataFromPost($post),
            'collections' => self::collectionsFormDataFromPost($post),
            'products' => self::productsFormDataFromPost($post),
            'partners' => self::partnersFormDataFromPost($post),
            default => [],
        };
    }

    public static function saveTab(BlockFormHandler $handler, int $pageId, string $tab, array $post): bool
    {
        $tab = self::normalizeTab($tab);
        $blocks = self::indexBlocksByKey($pageId);
        $ok = true;

        if ($tab === 'hero') {
            if (isset($blocks['hero'])) {
                $blocks['hero']->setDataArray($handler->dataFromPost($blocks['hero']->block_type, $post));
                $ok = $blocks['hero']->save(false) && $ok;
            }
            if (isset($blocks['seo'])) {
                $blocks['seo']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
                $ok = $blocks['seo']->save(false) && $ok;
            }

            return $ok;
        }

        if ($tab === 'collections') {
            if (isset($blocks['collections'])) {
                $blocks['collections']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_COLLECTIONS, $post));
                $ok = $blocks['collections']->save(false) && $ok;
            }
            if (isset($blocks['philosophy'])) {
                $blocks['philosophy']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_PHILOSOPHY, $post));
                $ok = $blocks['philosophy']->save(false) && $ok;
            }

            return $ok;
        }

        $blockKey = $tab;
        if (!isset($blocks[$blockKey])) {
            return false;
        }

        $block = $blocks[$blockKey];
        $block->setDataArray($handler->dataFromPost($block->block_type, $post));

        return $block->save(false);
    }

    /**
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    private static function buildHeroTabFormData(BlockFormHandler $handler, array $blocks): array
    {
        $formData = [];
        if (isset($blocks['hero'])) {
            $formData = $handler->dataToForm($blocks['hero']->block_type, $blocks['hero']->getDataArray());
        }
        if (isset($blocks['seo'])) {
            $formData = array_merge(
                $formData,
                $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $blocks['seo']->getDataArray())
            );
        }

        return $formData;
    }

    /**
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    private static function buildCollectionsTabFormData(BlockFormHandler $handler, array $blocks): array
    {
        $formData = [];
        if (isset($blocks['collections'])) {
            $formData = BlockFormBuilders::collectionsToForm($blocks['collections']->getDataArray());
        }
        if (isset($blocks['philosophy'])) {
            $formData = array_merge(
                $formData,
                $handler->dataToForm(BlockTypeRegistry::TYPE_PHILOSOPHY, $blocks['philosophy']->getDataArray())
            );
        }

        return $formData;
    }

    /**
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    private static function buildProductsTabFormData(BlockFormHandler $handler, array $blocks): array
    {
        if (!isset($blocks['products'])) {
            return BlockFormBuilders::homeProductsToForm([]);
        }

        return BlockFormBuilders::homeProductsToForm($blocks['products']->getDataArray());
    }

    /**
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    private static function buildPartnersTabFormData(BlockFormHandler $handler, array $blocks): array
    {
        if (!isset($blocks['partners'])) {
            return BlockFormBuilders::homePartnersToForm([]);
        }

        return BlockFormBuilders::homePartnersToForm($blocks['partners']->getDataArray());
    }

    /**
     * @return array<string, mixed>
     */
    private static function heroFormDataFromPost(array $post): array
    {
        return $post;
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectionsFormDataFromPost(array $post): array
    {
        $cards = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageCollectionsHelper::CARD_COUNT; $i++) {
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $slides = [];

            foreach (BlockFormPostHelper::rows($row['slides'] ?? null) as $slide) {
                if (!is_array($slide)) {
                    continue;
                }

                $slides[] = [
                    'label' => (string)($slide['label'] ?? ''),
                    'image_src' => (string)($slide['image_src'] ?? ''),
                    'image_alt' => (string)($slide['image_alt'] ?? ''),
                ];
            }

            $cards[] = [
                'catalog_direction_id' => (string)($row['catalog_direction_id'] ?? ''),
                'title_uppercase' => !empty($row['title_uppercase']),
                'slides' => HomePageCollectionsHelper::padSlidesForForm($slides),
            ];
        }

        return [
            'cards' => HomePageCollectionsHelper::padCardsForForm($cards),
            'philosophy_text' => (string)($post['philosophy_text'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function productsFormDataFromPost(array $post): array
    {
        $cards = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageProductsHelper::CARD_COUNT; $i++) {
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];

            $cards[] = [
                'catalog_product_id' => (string)($row['catalog_product_id'] ?? ''),
                'product_search' => (string)($row['product_search'] ?? ''),
                'image_src' => (string)($row['image_src'] ?? ''),
                'image_alt' => (string)($row['image_alt'] ?? ''),
            ];
        }

        return ['cards' => $cards];
    }

    /**
     * @return array<string, mixed>
     */
    private static function partnersFormDataFromPost(array $post): array
    {
        return $post;
    }
}
