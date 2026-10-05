<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageHomeHeroHelper
{
    public static function isHomeHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'home';
    }

    public static function shouldRedirectToHomeHero(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoHomeHero($block->page->slug, $block->block_key);
    }

    public static function isMergedIntoHomeHero(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'home' && $blockKey === 'seo';
    }

    public static function isAutoManagedOnHome(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'home' && $blockKey === 'journal';
    }

    public static function isHiddenFromHomeAdminList(string $pageSlug, string $blockKey): bool
    {
        return self::isMergedIntoHomeHero($pageSlug, $blockKey)
            || self::isAutoManagedOnHome($pageSlug, $blockKey);
    }

    /**
     * @return 'hero'|'blocks'|null
     */
    public static function homeBlockEditorRedirectTarget(ContentBlock $block): ?string
    {
        if ($block->page === null || $block->page->slug !== 'home') {
            return null;
        }

        if (self::isMergedIntoHomeHero($block->page->slug, $block->block_key)) {
            return 'hero';
        }

        if (self::isAutoManagedOnHome($block->page->slug, $block->block_key)) {
            return 'blocks';
        }

        return null;
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function filterBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'home') {
            return $blocks;
        }

        return array_values(array_filter(
            $blocks,
            static fn (ContentBlock $block): bool => !self::isHiddenFromHomeAdminList($pageSlug, $block->block_key)
        ));
    }

    /**
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    public static function mergeRelatedFormData(ContentBlock $heroBlock, BlockFormHandler $handler, array $formData): array
    {
        if (!self::isHomeHeroBlock($heroBlock)) {
            return $formData;
        }

        $seo = self::findRelatedBlock($heroBlock, 'seo');
        if ($seo !== null) {
            $formData = array_merge(
                $formData,
                $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $seo->getDataArray())
            );
        }

        return $formData;
    }

    public static function saveRelatedBlocks(ContentBlock $heroBlock, BlockFormHandler $handler, array $post): void
    {
        if (!self::isHomeHeroBlock($heroBlock)) {
            return;
        }

        $seo = self::findRelatedBlock($heroBlock, 'seo');
        if ($seo !== null) {
            $seo->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
            $seo->save(false);
        }
    }

    public static function findHomeHeroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->one();
    }

    private static function findRelatedBlock(ContentBlock $heroBlock, string $key): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $heroBlock->page_id, 'block_key' => $key])
            ->one();
    }
}
