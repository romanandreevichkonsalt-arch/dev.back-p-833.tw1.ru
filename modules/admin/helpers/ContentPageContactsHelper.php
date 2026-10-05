<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageContactsHelper
{
    use ContentPageTabEditorTrait;

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'info',
            'contact',
            'regions',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'info' => 'Контактные данные',
            'contact' => 'Форма заявки',
            'regions' => 'Регионы и салоны',
        ];
    }

    public static function blockLabel(string $blockKey): string
    {
        return self::blockLabels()[$blockKey] ?? ContentBlockUi::keyLabel($blockKey);
    }

    public static function isContactsHeroBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'hero'
            && $block->page !== null
            && $block->page->slug === 'contacts';
    }

    public static function isContactsContactBlock(ContentBlock $block): bool
    {
        return $block->block_key === 'contact'
            && $block->page !== null
            && $block->page->slug === 'contacts'
            && $block->block_type === BlockTypeRegistry::TYPE_CONTACT_CTA;
    }

    public static function isHiddenFromContactsAdminList(string $pageSlug, string $blockKey): bool
    {
        return $pageSlug === 'contacts' && $blockKey === 'seo';
    }

    public static function shouldRedirectToContactsHero(ContentBlock $block): bool
    {
        return $block->page !== null
            && self::isHiddenFromContactsAdminList($block->page->slug, $block->block_key);
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function filterBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'contacts') {
            return $blocks;
        }

        return array_values(array_filter(
            $blocks,
            static fn (ContentBlock $block): bool => !self::isHiddenFromContactsAdminList($pageSlug, $block->block_key)
        ));
    }

    /**
     * @param ContentBlock[] $blocks
     * @return ContentBlock[]
     */
    public static function sortBlocksForAdminList(string $pageSlug, array $blocks): array
    {
        if ($pageSlug !== 'contacts') {
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
        if (self::isContactsHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $formData = array_merge(
                    $formData,
                    $handler->dataToForm(BlockTypeRegistry::TYPE_SEO, $seo->getDataArray())
                );
            }
        }

        return $formData;
    }

    public static function saveRelatedBlocks(ContentBlock $block, BlockFormHandler $handler, array $post): void
    {
        if (self::isContactsHeroBlock($block)) {
            $seo = self::findRelatedBlock($block, 'seo');
            if ($seo !== null) {
                $seo->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post));
                $seo->save(false);
            }
        }
    }

    public static function findContactsHeroBlock(int $pageId): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
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
            'info' => 'Телефон, email, адрес и ссылки на Telegram, ВКонтакте и MAX. Подписи формы — в блоке «Форма заявки».',
            'contact' => 'Фото слева, заголовки формы и PDF политики конфиденциальности.',
            'regions' => 'Регионы, салоны и точки на карте.',
        ];
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'contacts';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'contacts';
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'contacts') {
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

    private static function findRelatedBlock(ContentBlock $block, string $key): ?ContentBlock
    {
        return ContentBlock::find()
            ->where(['page_id' => $block->page_id, 'block_key' => $key])
            ->one();
    }
}
