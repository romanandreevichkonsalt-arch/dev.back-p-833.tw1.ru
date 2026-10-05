<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;

class ContentPageAboutHelper
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
            'community',
            'timeline',
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
            'community' => 'Галерея',
            'timeline' => 'История',
        ];
    }

    public static function isHiddenFromAboutAdminList(string $pageSlug, string $blockKey): bool
    {
        if ($pageSlug !== 'about') {
            return false;
        }

        return in_array($blockKey, ['seo', 'jobsIntro'], true);
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'about';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'about';
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
            'hero' => 'SEO, фото баннера и тексты на баннере.',
            'intro' => 'Изображение и текст с абзацами после баннера.',
            'community' => 'Заголовок и неограниченное количество фото.',
            'timeline' => 'Этапы истории с галереей и блок «Создавайте вместе с нами».',
        ];
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'about') {
            return 'hero';
        }

        return match ($block->block_key) {
            'seo' => 'hero',
            'jobsIntro' => 'timeline',
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

        if ($tab === 'timeline') {
            return self::buildTimelineTabFormData($handler, $blocks);
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

        if ($tab === 'timeline') {
            return self::saveTimelineTab($handler, $blocks, $post);
        }

        if (!isset($blocks[$tab])) {
            return false;
        }

        $blocks[$tab]->setDataArray($handler->dataFromPost($blocks[$tab]->block_type, $post));

        return $blocks[$tab]->save(false);
    }

    /**
     * @param array<string, ContentBlock> $blocks
     * @return array<string, mixed>
     */
    private static function buildTimelineTabFormData(BlockFormHandler $handler, array $blocks): array
    {
        $formData = [];
        if (isset($blocks['timeline'])) {
            $formData = $handler->dataToForm($blocks['timeline']->block_type, $blocks['timeline']->getDataArray());
        }
        if (isset($blocks['jobsIntro'])) {
            $jobsIntro = $handler->dataToForm(BlockTypeRegistry::TYPE_TITLE_TEXT, $blocks['jobsIntro']->getDataArray());
            $formData['jobs_intro_title'] = $jobsIntro['title'] ?? '';
            $formData['jobs_intro_text'] = $jobsIntro['text'] ?? '';
        }

        return $formData;
    }

    /**
     * @param array<string, ContentBlock> $blocks
     */
    private static function saveTimelineTab(BlockFormHandler $handler, array $blocks, array $post): bool
    {
        $ok = true;
        if (isset($blocks['timeline'])) {
            $blocks['timeline']->setDataArray($handler->dataFromPost($blocks['timeline']->block_type, $post));
            $ok = $blocks['timeline']->save(false) && $ok;
        }
        if (isset($blocks['jobsIntro'])) {
            $blocks['jobsIntro']->setDataArray($handler->dataFromPost(BlockTypeRegistry::TYPE_TITLE_TEXT, [
                'title' => $post['jobs_intro_title'] ?? '',
                'text' => $post['jobs_intro_text'] ?? '',
            ]));
            $ok = $blocks['jobsIntro']->save(false) && $ok;
        }

        return $ok;
    }
}
