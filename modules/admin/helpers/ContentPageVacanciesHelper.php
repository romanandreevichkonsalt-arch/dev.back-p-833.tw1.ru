<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockFormHandler;
use app\services\vacancy\VacancyDirectionsAdminService;

class ContentPageVacanciesHelper
{
    use ContentPageTabEditorTrait;

    /**
     * @return string[]
     */
    public static function adminBlockOrder(): array
    {
        return [
            'hero',
            'values',
            'gallery',
            'groups',
            'jobs',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'values' => 'Вступление',
            'gallery' => 'Галерея',
            'groups' => 'Направления',
            'jobs' => 'Вакансии',
        ];
    }

    public static function isHiddenFromVacanciesAdminList(string $pageSlug, string $blockKey): bool
    {
        if ($pageSlug !== 'vacancies') {
            return false;
        }

        return $blockKey === 'seo';
    }

    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'vacancies';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'vacancies';
    }

    /**
     * @return array<string, string>
     */
    public static function tabLabels(): array
    {
        return [
            'hero' => self::blockLabels()['hero'],
            'values' => self::blockLabels()['values'],
            'gallery' => self::blockLabels()['gallery'],
            'groups' => self::blockLabels()['groups'],
            'jobs' => self::blockLabels()['jobs'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tabLeads(): array
    {
        return [
            'hero' => 'SEO, фото баннера, подпись, заголовок и подзаголовок (без кнопки).',
            'values' => 'Заголовок, изображение, галерея и текст с абзацами.',
            'gallery' => 'Неограниченное количество фото.',
            'groups' => 'Направления вакансий: номер, slug, название, описание и тексты пустого блока.',
            'jobs' => 'Список вакансий по направлениям.',
        ];
    }

    public static function normalizeTab(string $tab): string
    {
        return in_array($tab, self::adminBlockOrder(), true) ? $tab : 'hero';
    }

    public static function tabForBlock(ContentBlock $block): string
    {
        if ($block->page === null || $block->page->slug !== 'vacancies') {
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

        if ($tab === 'groups') {
            return (new VacancyDirectionsAdminService())->buildFormData();
        }

        if ($tab === 'jobs') {
            return [];
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

        if ($tab === 'groups') {
            return (new VacancyDirectionsAdminService())->save($post);
        }

        if ($tab === 'jobs') {
            return true;
        }

        if (!isset($blocks[$tab])) {
            return false;
        }

        $blocks[$tab]->setDataArray($handler->dataFromPost($blocks[$tab]->block_type, $post));

        return $blocks[$tab]->save(false);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function defaultGroupTemplates(): array
    {
        return [
            [
                'id' => 'production',
                'number' => '01',
                'title' => 'Производство и разработка',
                'description' => 'Мастера цеха, конструкторы и технологи — все, кто причастен к созданию наших предметов мебели.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'product_design',
                'number' => '02',
                'title' => 'Продукт и дизайн',
                'description' => 'Дизайнеры, проектировщики и специалисты по материалам — те, кто придумывает эстетику, форму и характер наших будущих коллекций.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'logistics',
                'number' => '03',
                'title' => 'Управление и логистика',
                'description' => 'Специалисты снабжения, логисты и специалисты склада — те, кто обеспечивает производство лучшим сырьём и бережно доставляет готовую мебель клиентам.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'client_experience',
                'number' => '04',
                'title' => 'Клиентский опыт',
                'description' => 'Консультанты шоурумов, менеджеры сервиса — все, кто помогает сделать путь к идеальному интерьеру лёгким и приятным.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
            [
                'id' => 'management',
                'number' => '05',
                'title' => 'Управление и администрация',
                'description' => 'Руководители направлений, операционные специалисты и управляющий персонал — те, кто координирует процессы и развивает команду.',
                'emptyTitle' => 'Сейчас у нас нет открытых вакансий',
                'emptyDescription' => 'Следите за обновлениями — новые позиции появятся здесь',
            ],
        ];
    }
}
