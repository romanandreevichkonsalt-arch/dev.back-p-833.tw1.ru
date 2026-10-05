<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

/**
 * Общие методы вкладочного редактора страниц контента.
 */
trait ContentPageTabEditorTrait
{
    /**
     * @return array<string, ContentBlock>
     */
    protected static function indexBlocksByKey(int $pageId): array
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
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    protected static function buildHeroTabFormData(BlockFormHandler $handler, array $blocks): array
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
     */
    protected static function saveHeroTab(BlockFormHandler $handler, array $blocks, array $post): bool
    {
        $ok = true;
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
}
