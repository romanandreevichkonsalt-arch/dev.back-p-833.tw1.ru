<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;

class ContentPageBuildingHelper
{
    use ContentPageTabEditorTrait;

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'intro',
            'comfort',
            'stack',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'intro' => 'Вступление',
            'comfort' => 'Комфорт',
            'stack' => 'Технологии',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function isBuildingHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'building';
    }

    public static function isHiddenFromBuildingAdminList(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'building' && in_array($blockKey, ['seo', 'standards', 'ethics'], true);
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
            'intro' => 'Вводный абзац после баннера.',
            'comfort' => 'Блоки о комфорте на странице производства.',
            'stack' => 'Этика и стандарты, затем блоки о технологиях и экологии.',
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'building';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'building';
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'building') {
            return 'hero';
        }

        return match ($block->block_key) {
            'seo' => 'hero',
            'standards', 'ethics' => 'stack',
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

        if (isset($blocks[$tab])) {
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

        if (!isset($blocks[$tab])) {
            return false;
        }

        $blocks[$tab]->setDataArray($handler->dataFromPost($blocks[$tab]->block_type, $post));

        return $blocks[$tab]->save(false);
    }
}
