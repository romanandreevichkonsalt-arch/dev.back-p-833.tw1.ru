<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;

class ContentPageLibraryHelper
{
    use ContentPageTabEditorTrait;

    /**
     * @var string[]
     */
    private const CONTENT_BLOCK_TABS = ['implementedModels', 'yourIdea', 'documents'];

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return ['hero', ...self::CONTENT_BLOCK_TABS];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'implementedModels' => 'Реализованные модели',
            'yourIdea' => 'Ваша идея',
            'documents' => 'Документы',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'library';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'library';
    }

    /**
     * @return array<string, string>
     */
    public static function tabLabels(): array
    {
        return self::blockLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function tabLeads(): array
    {
        return [
            'hero' => 'SEO, фото баннера (компьютер и телефон) и заголовки страницы.',
            'implementedModels' => 'Заголовок секции, описание и карточки проектов с фото.',
            'yourIdea' => 'Фото слева, заголовок, два абзаца и ссылка справа.',
            'documents' => 'Карточки PDF: название, подзаголовок и файл из медиатеки.',
        ];
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'library') {
            return 'hero';
        }

        return match ($block->block_key) {
            'seo' => 'hero',
            default => self::normalizeTab($block->block_key),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildFormDataForTab(BlockFormHandler $handler, int $pageId, string $tab): array
    {
        $tab = self::normalizeTab($tab);
        $blocks = self::indexBlocksByKey($pageId);

        if ($tab === 'hero') {
            return self::buildHeroTabFormData($handler, $blocks);
        }

        if (in_array($tab, self::CONTENT_BLOCK_TABS, true) && isset($blocks[$tab])) {
            return $handler->dataToForm($blocks[$tab]->block_type, $blocks[$tab]->getDataArray());
        }

        return [];
    }

    public static function saveTab(BlockFormHandler $handler, int $pageId, string $tab, array $post): bool
    {
        $tab = self::normalizeTab($tab);
        $blocks = self::indexBlocksByKey($pageId);

        if ($tab === 'hero') {
            return self::saveHeroTab($handler, $blocks, $post);
        }

        if (in_array($tab, self::CONTENT_BLOCK_TABS, true) && isset($blocks[$tab])) {
            $block = $blocks[$tab];
            $block->setDataArray($handler->dataFromPost($block->block_type, $post));

            return $block->save(false);
        }

        return false;
    }
}
