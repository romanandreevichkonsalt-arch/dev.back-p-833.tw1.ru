<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageDesignersHelper
{
    use ContentPageTabEditorTrait;

    public const MISSION_SLIDE_COUNT = 4;
    public const MATERIALS_ITEM_COUNT = 3;
    public const GALLERY_PHOTO_MIN_COUNT = 3;

    /**
     * Порядок блоков в админке (seo — в баннере, mission — во вступлении).
     *
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'intro',
            'materials',
            'gallery',
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
            'gallery' => 'Галерея',
            'materials' => 'Материалы',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function isDesignersHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'designers';
    }

    public static function isDesignersIntroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'intro'
            && $block->page !== null
            && $block->page->slug === 'designers';
    }

    public static function isDesignersMaterialsBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'materials'
            && $block->page !== null
            && $block->page->slug === 'designers'
            && $block->block_type === BlockTypeRegistry::TYPE_MATERIALS_SECTION;
    }

    public static function isDesignersGalleryBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'gallery'
            && $block->page !== null
            && $block->page->slug === 'designers'
            && $block->block_type === BlockTypeRegistry::TYPE_GALLERY_STACK_SECTION;
    }

    public static function isMergedIntoDesignersHero(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'designers' && $blockKey === 'seo';
    }

    public static function isMergedIntoDesignersIntro(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'designers' && $blockKey === 'mission';
    }

    public static function isHiddenFromDesignersAdminList(string $pageSlug, string $blockKey): bool
    {
        if ($pageSlug !== 'designers') {
            return false;
        }

        return in_array($blockKey, ['seo', 'mission', 'samples', 'photoStack', 'contact'], true);
    }

    public static function shouldRedirectToDesignersHero(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoDesignersHero($block->page->slug, $block->block_key);
    }

    public static function shouldRedirectToDesignersIntro(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoDesignersIntro($block->page->slug, $block->block_key);
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function filterBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'designers') {
            return $blocks;
        }

        return array_values(array_filter(
            $blocks,
            static fn (ContentBlock $block): bool => !self::isHiddenFromDesignersAdminList($pageSlug, $block->block_key)
        ));
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function sortBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'designers') {
            return $blocks;
        }

        $order = array_flip(self::adminBlockOrder());

        usort($blocks, static function (ContentBlock $a, ContentBlock $b) use ($order): int {
            $posA = $order[$a->block_key] ?? PHP_INT_MAX;
            $posB = $order[$b->block_key] ?? PHP_INT_MAX;

            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return $a->id <=> $b->id;
        });

        return $blocks;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function padMissionItemsForForm(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::MISSION_SLIDE_COUNT) {
            $normalized[] = [
                'title' => '',
                'text' => '',
                'image_src' => '',
                'image_alt' => '',
            ];
        }

        return array_slice($normalized, 0, self::MISSION_SLIDE_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function padMaterialsItemsForForm(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::MATERIALS_ITEM_COUNT) {
            $normalized[] = [
                'title' => '',
                'text' => '',
            ];
        }

        return array_slice($normalized, 0, self::MATERIALS_ITEM_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    public static function padGalleryPhotosForForm(array $photos): array
    {
        $normalized = [];
        foreach ($photos as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::GALLERY_PHOTO_MIN_COUNT) {
            $normalized[] = [
                'image_src' => '',
                'image_alt' => '',
                'rotate' => '',
                'offset_x' => '',
                'offset_y' => '',
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    public static function mergeRelatedFormData(ContentBlock $block, BlockFormHandler $handler, array $formData): array
    {
        if (self::isDesignersHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $formData = array_merge(
                    $formData,
                    $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $seo->getDataArray())
                );
            }
        }

        if (self::isDesignersIntroBlock($block)) {
            $mission = self::findRelatedBlock($block, 'mission');
            if ($mission !== null) {
                $missionForm = $handler->dataToForm(BlockTypeRegistry::TYPE_MISSION, $mission->getDataArray());
                $formData['items'] = self::padMissionItemsForForm($missionForm['items'] ?? []);
            }
        }

        return $formData;
    }

    public static function saveRelatedBlocks(ContentBlock $block, BlockFormHandler $handler, array $post): void
    {
        if (self::isDesignersHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $seo->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
                $seo->save(false);
            }
        }

        if (self::isDesignersIntroBlock($block)) {
            $mission = self::findRelatedBlock($block, 'mission');
            if ($mission !== null) {
                $mission->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_MISSION, $post));
                $mission->save(false);
            }
        }
    }

    public static function findDesignersHeroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->one();
    }

    public static function findDesignersIntroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'intro'])
            ->one();
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
            'intro' => 'Текст, фото слева и 4 слайдов преимуществ.',
            'materials' => 'Фото на фоне, текст, архив и три колонки.',
            'gallery' => 'Текст и стопка фото.',
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'designers';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'designers';
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'designers') {
            return 'hero';
        }

        return match ($block->block_key) {
            'seo' => 'hero',
            'mission' => 'intro',
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

        if ($tab === 'intro' && isset($blocks['intro'])) {
            $formData = $handler->dataToForm($blocks['intro']->block_type, $blocks['intro']->getDataArray());

            return self::mergeRelatedFormData($blocks['intro'], $handler, $formData);
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

        if ($tab === 'intro' && isset($blocks['intro'])) {
            $blocks['intro']->setDataArray($handler->dataFromPost($blocks['intro']->block_type, $post));
            $ok = $blocks['intro']->save(false);
            self::saveRelatedBlocks($blocks['intro'], $handler, $post);

            return $ok;
        }

        if (!isset($blocks[$tab])) {
            return false;
        }

        $blocks[$tab]->setDataArray($handler->dataFromPost($blocks[$tab]->block_type, $post));

        return $blocks[$tab]->save(false);
    }

    private static function findRelatedBlock(ContentBlock $block, string $key): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $block->page_id, 'block_key' => $key])
            ->one();
    }
}
