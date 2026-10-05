<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormBuilders;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageJournalHelper
{
    use ContentPageTabEditorTrait;

    public const CATEGORY_COUNT = 4;

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'categories',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'categories' => 'Вкладки',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function isHiddenFromJournalAdminList(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'journal' && $blockKey === 'seo';
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'journal';
    }

    public static function shouldRedirectToJournalPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'journal';
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
            'categories' => 'Четыре вкладки категорий.',
        ];
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'journal') {
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

        if ($tab === 'categories' && isset($blocks['categories'])) {
            $categoriesData = $handler->dataToForm(
                $blocks['categories']->block_type,
                $blocks['categories']->getDataArray()
            );

            return [
                'categories' => self::padJournalCategoriesForForm($categoriesData['categories'] ?? []),
            ];
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

        if ($tab === 'categories' && isset($blocks['categories'])) {
            $categories = $blocks['categories'];
            $existing = $categories->getDataArray();
            $categories->setDataArray(
                BlockFormBuilders::journalCategoryTabsFromPost($post, is_array($existing) ? $existing : [])
            );

            return $categories->save(false);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     * @deprecated use buildFormDataForTab
     */
    public static function buildUnifiedFormData(BlockFormHandler $handler, int $pageId): array
    {
        return self::buildFormDataForTab($handler, $pageId, 'hero');
    }

    /**
     * @deprecated use saveTab per tab
     */
    public static function saveUnifiedBlocks(BlockFormHandler $handler, int $pageId, array $post): bool
    {
        $ok = self::saveTab($handler, $pageId, 'hero', $post);

        return self::saveTab($handler, $pageId, 'categories', $post) && $ok;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public static function padJournalCategoriesForForm(array $categories): array
    {
        $defaults = self::defaultCategoryTemplates();
        $byId = [];

        foreach ($categories as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }

        $result = [];
        foreach ($defaults as $template) {
            $id = $template['id'];
            $merged = $byId[$id] ?? [];

            $result[] = [
                'id' => $id,
                'icon' => trim((string)($merged['icon'] ?? '')) ?: $template['icon'],
                'label' => trim((string)($merged['label'] ?? '')) ?: $template['label'],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function defaultCategoryTemplates(): array
    {
        return [
            [
                'id' => 'process',
                'label' => 'Архитектура процессов',
                'icon' => 'journal-process',
            ],
            [
                'id' => 'interior',
                'label' => 'Интерьерные проекты',
                'icon' => 'journal-interior',
            ],
            [
                'id' => 'textures',
                'label' => 'Исследование фактур',
                'icon' => 'journal-textures',
            ],
            [
                'id' => 'interviews',
                'label' => 'Интервью с дизайнерами и командой',
                'icon' => 'journal-interviews',
            ],
        ];
    }
}
