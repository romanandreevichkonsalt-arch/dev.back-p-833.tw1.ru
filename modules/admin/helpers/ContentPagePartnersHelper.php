<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPagePartnersHelper
{
    use ContentPageTabEditorTrait;

    public const MISSION_SLIDE_COUNT = 5;
    public const FORMATS_ITEM_COUNT = 4;
    public const AUDIENCE_CARD_COUNT = 3;
    public const AUDIENCE_STATS_COUNT = 3;
    public const SALON_FORMAT_COUNT = 3;
    public const SALON_CONDITIONS_COUNT = 3;
    public const PRESENTATION_ITEM_COUNT = 4;

    /**
     * Порядок блоков в админке страницы «Франшиза».
     * Скрыты: seo (в баннере), mission (во вступлении), gallery, terms (без UI).
     *
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'intro',
            'formats',
            'audience',
            'salonFormats',
            'presentation',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'intro' => 'О бренде',
            'formats' => 'Партнёрство',
            'audience' => 'Кому подойдёт',
            'salonFormats' => 'Форматы партнёрства',
            'presentation' => 'Презентация',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function isPartnersHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'partners';
    }

    public static function isPartnersIntroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'intro'
            && $block->page !== null
            && $block->page->slug === 'partners';
    }

    public static function isPartnersFormatsBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'formats'
            && $block->page !== null
            && $block->page->slug === 'partners'
            && $block->block_type === BlockTypeRegistry::TYPE_FORMATS_SECTION;
    }

    public static function isPartnersAudienceBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'audience'
            && $block->page !== null
            && $block->page->slug === 'partners'
            && $block->block_type === BlockTypeRegistry::TYPE_AUDIENCE_SECTION;
    }

    public static function isPartnersSalonFormatsBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'salonFormats'
            && $block->page !== null
            && $block->page->slug === 'partners'
            && $block->block_type === BlockTypeRegistry::TYPE_SALON_FORMATS_SECTION;
    }

    public static function isPartnersPresentationBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'presentation'
            && $block->page !== null
            && $block->page->slug === 'partners'
            && $block->block_type === BlockTypeRegistry::TYPE_PRESENTATION_SECTION;
    }

    public static function isMergedIntoPartnersHero(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'partners' && $blockKey === 'seo';
    }

    public static function isMergedIntoPartnersIntro(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'partners' && $blockKey === 'mission';
    }

    public static function isHiddenFromPartnersAdminList(string $pageSlug, string $blockKey): bool
    {
        if ($pageSlug !== 'partners') {
            return false;
        }

        return in_array($blockKey, ['seo', 'mission', 'gallery', 'terms', 'contact'], true);
    }

    public static function shouldRedirectToPartnersHero(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoPartnersHero($block->page->slug, $block->block_key);
    }

    public static function shouldRedirectToPartnersIntro(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isMergedIntoPartnersIntro($block->page->slug, $block->block_key);
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function filterBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'partners') {
            return $blocks;
        }

        return array_values(array_filter(
            $blocks,
            static fn (ContentBlock $block): bool => !self::isHiddenFromPartnersAdminList($pageSlug, $block->block_key)
        ));
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function sortBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'partners') {
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
    public static function padFormatsItemsForForm(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::FORMATS_ITEM_COUNT) {
            $index = count($normalized) + 1;
            $normalized[] = [
                'number' => sprintf('%02d', $index),
                'text' => '',
            ];
        }

        foreach ($normalized as $i => &$row) {
            if (trim((string)($row['number'] ?? '')) === '') {
                $row['number'] = sprintf('%02d', $i + 1);
            }
        }
        unset($row);

        return array_slice($normalized, 0, self::FORMATS_ITEM_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function padAudienceCardsForForm(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::AUDIENCE_CARD_COUNT) {
            $index = count($normalized) + 1;
            $normalized[] = [
                'number' => sprintf('%02d', $index),
                'title' => '',
                'text' => '',
            ];
        }

        foreach ($normalized as $i => &$row) {
            if (trim((string)($row['number'] ?? '')) === '') {
                $row['number'] = sprintf('%02d', $i + 1);
            }
        }
        unset($row);

        return array_slice($normalized, 0, self::AUDIENCE_CARD_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $stats
     * @return array<int, array<string, mixed>>
     */
    public static function padAudienceStatsForForm(array $stats): array
    {
        $normalized = [];
        foreach ($stats as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::AUDIENCE_STATS_COUNT) {
            $normalized[] = ['text' => ''];
        }

        return array_slice($normalized, 0, self::AUDIENCE_STATS_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function padSalonFormatsForForm(array $items): array
    {
        $defaults = [
            ['id' => 'start', 'title' => 'Старт'],
            ['id' => 'comfort', 'title' => 'Комфорт'],
            ['id' => 'vip', 'title' => 'ВИП'],
        ];

        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::SALON_FORMAT_COUNT) {
            $index = count($normalized);
            $normalized[] = array_merge(
                [
                    'id' => '',
                    'title' => '',
                    'text' => '',
                    'area' => '',
                    'assortment' => '',
                    'profit' => '',
                    'employees' => '',
                    'investment' => '',
                ],
                $defaults[$index] ?? []
            );
        }

        foreach ($normalized as $i => &$row) {
            if (trim((string)($row['id'] ?? '')) === '' && isset($defaults[$i]['id'])) {
                $row['id'] = $defaults[$i]['id'];
            }
        }
        unset($row);

        return array_slice($normalized, 0, self::SALON_FORMAT_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @return array<int, array<string, mixed>>
     */
    public static function padSalonConditionsForForm(array $conditions): array
    {
        $defaults = [
            ['label' => 'Паушальный взнос', 'value' => '0 ₽'],
            ['label' => 'Роялти', 'value' => '0 ₽'],
            ['label' => 'Срок окупаемости', 'value' => 'Около 4 месяцев'],
        ];

        $normalized = [];
        foreach ($conditions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::SALON_CONDITIONS_COUNT) {
            $index = count($normalized);
            $normalized[] = $defaults[$index] ?? ['label' => '', 'value' => ''];
        }

        return array_slice($normalized, 0, self::SALON_CONDITIONS_COUNT);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function padPresentationItemsForForm(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::PRESENTATION_ITEM_COUNT) {
            $index = count($normalized) + 1;
            $normalized[] = [
                'number' => sprintf('%02d', $index),
                'text' => '',
            ];
        }

        foreach ($normalized as $i => &$row) {
            if (trim((string)($row['number'] ?? '')) === '') {
                $row['number'] = sprintf('%02d', $i + 1);
            }
        }
        unset($row);

        return array_slice($normalized, 0, self::PRESENTATION_ITEM_COUNT);
    }

    /**
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    public static function mergeRelatedFormData(ContentBlock $block, BlockFormHandler $handler, array $formData): array
    {
        if (self::isPartnersHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $formData = array_merge(
                    $formData,
                    $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $seo->getDataArray())
                );
            }
        }

        if (self::isPartnersIntroBlock($block)) {
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
        if (self::isPartnersHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $seo->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
                $seo->save(false);
            }
        }

        if (self::isPartnersIntroBlock($block)) {
            $mission = self::findRelatedBlock($block, 'mission');
            if ($mission !== null) {
                $mission->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_MISSION, $post));
                $mission->save(false);
            }
        }
    }

    public static function findPartnersHeroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->one();
    }

    public static function findPartnersIntroBlock(int $pageId): ?ContentBlock
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
            'intro' => 'Текст, большое фото слева и 5 слайдов преимуществ.',
            'formats' => 'Заголовки, сетка «Вы получите» и фото справа.',
            'audience' => 'Заголовок, баннер, 3 карточки и строка показателей.',
            'salonFormats' => 'Три формата салона и общие условия партнёрства.',
            'presentation' => 'Список преимуществ, кнопка PDF и фото справа.',
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'partners';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'partners';
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'partners') {
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
