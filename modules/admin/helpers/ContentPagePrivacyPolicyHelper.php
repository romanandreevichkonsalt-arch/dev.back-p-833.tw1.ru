<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;
use app\services\journal\JournalArticleBlockBuilder;

class ContentPagePrivacyPolicyHelper
{
    use ContentPageTabEditorTrait;

    public const TAB_CONTENT = 'content';

    /**
     * @return string[]
     */
    public static function allowedBlockTypes(): array
    {
        return [
            JournalArticleBlockBuilder::TYPE_HEADING,
            JournalArticleBlockBuilder::TYPE_TEXT,
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'privacy-policy';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'privacy-policy';
    }

    public static function normalizeTab(string $tab): string
    {
        return self::TAB_CONTENT;
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        return self::TAB_CONTENT;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildFormDataForTab(BlockFormHandler $handler, int $pageId, string $tab): array
    {
        unset($handler, $tab);

        return [
            'blocksForm' => JournalArticleBlockBuilder::blocksToForm(self::storedBlocks($pageId)),
        ];
    }

    public static function saveTab(BlockFormHandler $handler, int $pageId, string $tab, array $post): bool
    {
        unset($handler, $tab);

        $blocks = self::indexBlocksByKey($pageId);
        $block = $blocks['blocks'] ?? null;
        if ($block === null) {
            return false;
        }

        $block->setDataArray(JournalArticleBlockBuilder::blocksFromPost($post));

        return $block->save(false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDataFromPost(array $post): array
    {
        return [
            'blocksForm' => JournalArticleBlockBuilder::blocksToForm(
                JournalArticleBlockBuilder::blocksFromPost($post)
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function storedBlocks(int $pageId): array
    {
        $blocks = self::indexBlocksByKey($pageId);
        $content = $blocks['blocks'] ?? null;
        if ($content === null) {
            return [];
        }

        $data = $content->getDataArray();
        if ($data === []) {
            return [];
        }

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            return $data['blocks'];
        }

        return array_is_list($data) ? $data : [];
    }
}
