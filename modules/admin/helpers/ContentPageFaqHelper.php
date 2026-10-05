<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageFaqHelper
{
    public const CATEGORY_COUNT = 4;

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'intro',
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
            'intro' => 'Вступление',
            'categories' => 'Вкладки и вопросы',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
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
            'hero' => 'SEO, фото баннера (компьютер и телефон) и тексты на баннере.',
            'intro' => 'Заголовок «FAQs» и подзаголовок под баннером.',
            'categories' => 'Четыре вкладки с вопросами и ответами.',
        ];
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'faq') {
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

        return match ($tab) {
            'hero' => self::buildHeroTabFormData($handler, $blocks),
            'intro' => isset($blocks['intro'])
                ? $handler->dataToForm($blocks['intro']->block_type, $blocks['intro']->getDataArray())
                : [],
            'categories' => [
                'categories' => self::padFaqCategoriesForForm(
                    isset($blocks['categories'])
                        ? ($handler->dataToForm(
                            $blocks['categories']->block_type,
                            $blocks['categories']->getDataArray()
                        )['categories'] ?? [])
                        : []
                ),
            ],
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

        if ($tab === 'intro' && isset($blocks['intro'])) {
            $blocks['intro']->setDataArray($handler->dataFromPost($blocks['intro']->block_type, $post));

            return $blocks['intro']->save(false);
        }

        if ($tab === 'categories' && isset($blocks['categories'])) {
            $blocks['categories']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_FAQ_CATEGORIES, $post));

            return $blocks['categories']->save(false);
        }

        return false;
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

    public static function isFaqHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'faq';
    }

    public static function isFaqIntroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'intro'
            && $block->page !== null
            && $block->page->slug === 'faq';
    }

    public static function isFaqCategoriesBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'categories'
            && $block->page !== null
            && $block->page->slug === 'faq'
            && $block->block_type === BlockTypeRegistry::TYPE_FAQ_CATEGORIES;
    }

    public static function isHiddenFromFaqAdminList(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'faq' && $blockKey === 'seo';
    }

    public static function shouldRedirectToFaqHero(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isHiddenFromFaqAdminList($block->page->slug, $block->block_key);
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'faq';
    }

    public static function shouldRedirectToFaqPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'faq';
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
    public static function buildUnifiedFormData(BlockFormHandler $handler, int $pageId): array
    {
        $blocks = self::indexBlocksByKey($pageId);
        $formData = [];

        if (isset($blocks['hero'])) {
            $hero = $blocks['hero'];
            $formData = $handler->dataToForm($hero->block_type, $hero->getDataArray());

            if (isset($blocks['seo'])) {
                $formData = array_merge(
                    $formData,
                    $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $blocks['seo']->getDataArray())
                );
            }
        }

        if (isset($blocks['intro'])) {
            $formData = array_merge(
                $formData,
                $handler->dataToForm($blocks['intro']->block_type, $blocks['intro']->getDataArray())
            );
        }

        if (isset($blocks['categories'])) {
            $categoriesData = $handler->dataToForm(
                $blocks['categories']->block_type,
                $blocks['categories']->getDataArray()
            );
            $formData['categories'] = self::padFaqCategoriesForForm($categoriesData['categories'] ?? []);
        }

        return $formData;
    }

    public static function saveUnifiedBlocks(BlockFormHandler $handler, int $pageId, array $post): bool
    {
        $blocks = self::indexBlocksByKey($pageId);
        $ok = true;

        if (isset($blocks['hero'])) {
            $hero = $blocks['hero'];
            $hero->setDataArray($handler->dataFromPost($hero->block_type, $post));
            $ok = $hero->save(false) && $ok;
        }

        if (isset($blocks['seo'])) {
            $blocks['seo']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
            $ok = $blocks['seo']->save(false) && $ok;
        }

        if (isset($blocks['intro'])) {
            $intro = $blocks['intro'];
            $intro->setDataArray($handler->dataFromPost($intro->block_type, $post));
            $ok = $intro->save(false) && $ok;
        }

        if (isset($blocks['categories'])) {
            $categories = $blocks['categories'];
            $categories->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_FAQ_CATEGORIES, $post));
            $ok = $categories->save(false) && $ok;
        }

        return $ok;
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function filterBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'faq') {
            return $blocks;
        }

        return array_values(array_filter(
            $blocks,
            static fn (ContentBlock $block): bool => !self::isHiddenFromFaqAdminList($pageSlug, $block->block_key)
        ));
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function sortBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'faq') {
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
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    public static function mergeRelatedFormData(ContentBlock $block, BlockFormHandler $handler, array $formData): array
    {
        if (self::isFaqHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $formData = array_merge(
                    $formData,
                    $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $seo->getDataArray())
                );
            }
        }

        if (self::isFaqCategoriesBlock($block)) {
            $formData['categories'] = self::padFaqCategoriesForForm($formData['categories'] ?? []);
        }

        return $formData;
    }

    public static function saveRelatedBlocks(ContentBlock $block, BlockFormHandler $handler, array $post): void
    {
        if (self::isFaqHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $seo->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
                $seo->save(false);
            }
        }
    }

    public static function findFaqHeroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->one();
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public static function padFaqCategoriesForForm(array $categories): array
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
            $items = $merged['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $result[] = [
                'id' => $id,
                'icon' => trim((string)($merged['icon'] ?? '')) ?: $template['icon'],
                'label' => trim((string)($merged['label'] ?? '')) ?: $template['label'],
                'items' => $items,
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
                'id' => 'order',
                'label' => 'Заказ и подбор мебели',
                'icon' => 'faq-order',
            ],
            [
                'id' => 'payment',
                'label' => 'Оплата и доставка',
                'icon' => 'faq-payment',
            ],
            [
                'id' => 'materials',
                'label' => 'Материалы и уход',
                'icon' => 'faq-materials',
            ],
            [
                'id' => 'cooperation',
                'label' => 'Сотрудничество',
                'icon' => 'faq-cooperation',
            ],
        ];
    }

    private static function findRelatedBlock(ContentBlock $block, string $key): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $block->page_id, 'block_key' => $key])
            ->one();
    }
}
