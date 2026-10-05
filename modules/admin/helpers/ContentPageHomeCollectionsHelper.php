<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageHomeCollectionsHelper
{
    public static function isHomeCollectionsBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'collections'
            && $block->page !== null
            && $block->page->slug === 'home'
            && $block->block_type === BlockTypeRegistry::TYPE_COLLECTIONS;
    }

    public static function shouldRedirectToHomeCollections(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoHomeCollections($block->page->slug, $block->block_key);
    }

    public static function isMergedIntoHomeCollections(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'home' && $blockKey === 'philosophy';
    }

    public static function isHiddenFromHomeAdminList(string $pageSlug, string $blockKey): bool
    {
        return self::isMergedIntoHomeCollections($pageSlug, $blockKey);
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
    public static function mergeRelatedFormData(ContentBlock $block, BlockFormHandler $handler, array $formData): array
    {
        if (!self::isHomeCollectionsBlock($block)) {
            return $formData;
        }

        $philosophy = self::findRelatedBlock($block, 'philosophy');
        if ($philosophy !== null) {
            $formData = array_merge(
                $formData,
                $handler->dataToForm(BlockTypeRegistry::TYPE_PHILOSOPHY, $philosophy->getDataArray())
            );
        }

        return $formData;
    }

    public static function saveRelatedBlocks(ContentBlock $block, BlockFormHandler $handler, array $post): void
    {
        if (!self::isHomeCollectionsBlock($block)) {
            return;
        }

        $philosophy = self::findRelatedBlock($block, 'philosophy');
        if ($philosophy !== null) {
            $philosophy->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_PHILOSOPHY, $post));
            $philosophy->save(false);
        }
    }

    public static function findHomeCollectionsBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'collections'])
            ->one();
    }

    private static function findRelatedBlock(ContentBlock $block, string $key): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $block->page_id, 'block_key' => $key])
            ->one();
    }
}
