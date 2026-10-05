<?php

namespace app\services\content;

class BlockTypeRegistry
{
    public const TYPE_SEO = 'seo';
    public const TYPE_HERO_SIMPLE = 'hero_simple';
    public const TYPE_HERO_MEDIA = 'hero_media';
    public const TYPE_HERO_HOME = 'hero_home';
    public const TYPE_PHILOSOPHY = 'philosophy';
    public const TYPE_INTRO = 'intro';
    public const TYPE_INTRO_TEXT = 'intro_text';
    public const TYPE_CONTACT_CTA = 'contact_cta';
    public const TYPE_INFO_CONTACTS = 'info_contacts';
    public const TYPE_COLLECTIONS = 'collections';
    public const TYPE_HOME_PRODUCTS = 'home_products';
    public const TYPE_HOME_PARTNERS = 'home_partners';
    public const TYPE_HOME_JOURNAL = 'home_journal';
    public const TYPE_MISSION = 'mission';
    public const TYPE_FORMATS_LIST = 'formats_list';
    public const TYPE_FORMATS_SECTION = 'formats_section';
    public const TYPE_AUDIENCE_LIST = 'audience_list';
    public const TYPE_AUDIENCE_SECTION = 'audience_section';
    public const TYPE_SALON_FORMATS_SECTION = 'salon_formats_section';
    public const TYPE_GALLERY_SIMPLE = 'gallery_simple';
    public const TYPE_GALLERY_CAPTIONED = 'gallery_captioned';
    public const TYPE_GALLERY_STACK_SECTION = 'gallery_stack_section';
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRESENTATION = 'presentation';
    public const TYPE_PRESENTATION_SECTION = 'presentation_section';
    public const TYPE_MATERIALS_LIST = 'materials_list';
    public const TYPE_MATERIALS_SECTION = 'materials_section';
    public const TYPE_SAMPLES = 'samples';
    public const TYPE_PHOTO_STACK = 'photo_stack';
    public const TYPE_REGIONS = 'regions';
    public const TYPE_FAQ_CATEGORIES = 'faq_categories';
    public const TYPE_JOURNAL_CATEGORIES = 'journal_categories';
    public const TYPE_COMFORT_LIST = 'comfort_list';
    public const TYPE_STACK_LIST = 'stack_list';
    public const TYPE_STACK_SECTION = 'stack_section';
    public const TYPE_ETHICS = 'ethics';
    public const TYPE_TITLE_TEXT = 'title_text';
    public const TYPE_ABOUT_INTRO = 'about_intro';
    public const TYPE_ABOUT_GALLERY = 'about_gallery';
    public const TYPE_ABOUT_TIMELINE = 'about_timeline';
    public const TYPE_VACANCIES_VALUES = 'vacancies_values';
    public const TYPE_VACANCIES_GALLERY = 'vacancies_gallery';
    public const TYPE_VACANCIES_GROUPS = 'vacancies_groups';
    public const TYPE_LIBRARY_IMPLEMENTED_MODELS = 'library_implemented_models';
    public const TYPE_LIBRARY_YOUR_IDEA = 'library_your_idea';
    public const TYPE_LIBRARY_DOCUMENTS = 'library_documents';
    public const TYPE_JSON = 'json';
    public const TYPE_ARTICLE_BLOCKS = 'article_blocks';

    public static function labels(): array
    {
        return [
            self::TYPE_SEO => 'Настройки SEO',
            self::TYPE_HERO_SIMPLE => 'Баннер (только текст)',
            self::TYPE_HERO_MEDIA => 'Баннер с фото',
            self::TYPE_HERO_HOME => 'Баннер главной',
            self::TYPE_PHILOSOPHY => 'Текст философии',
            self::TYPE_INTRO => 'Вступление',
            self::TYPE_INTRO_TEXT => 'Текст вступления',
            self::TYPE_CONTACT_CTA => 'Блок с формой',
            self::TYPE_INFO_CONTACTS => 'Контакты',
            self::TYPE_COLLECTIONS => 'Коллекции',
            self::TYPE_HOME_PRODUCTS => 'Товары на главной',
            self::TYPE_HOME_PARTNERS => 'Вводный текст и карточки',
            self::TYPE_HOME_JOURNAL => 'Превью журнала',
            self::TYPE_MISSION => 'Миссия и ценности',
            self::TYPE_FORMATS_LIST => 'Список с фото',
            self::TYPE_FORMATS_SECTION => 'Блок с текстом и фото',
            self::TYPE_AUDIENCE_LIST => 'Список без фото',
            self::TYPE_AUDIENCE_SECTION => 'Аудитория с фото',
            self::TYPE_SALON_FORMATS_SECTION => 'Форматы салона',
            self::TYPE_GALLERY_SIMPLE => 'Галерея (фото)',
            self::TYPE_GALLERY_CAPTIONED => 'Галерея с подписью',
            self::TYPE_GALLERY_STACK_SECTION => 'Галерея со стопкой фото',
            self::TYPE_TERMS => 'Условия',
            self::TYPE_PRESENTATION => 'Презентация',
            self::TYPE_PRESENTATION_SECTION => 'Презентация с фото',
            self::TYPE_MATERIALS_LIST => 'Материалы (список)',
            self::TYPE_MATERIALS_SECTION => 'Материалы с фото',
            self::TYPE_SAMPLES => 'Образцы',
            self::TYPE_PHOTO_STACK => 'Стопка фото',
            self::TYPE_REGIONS => 'Регионы и салоны',
            self::TYPE_FAQ_CATEGORIES => 'Категории FAQ',
            self::TYPE_JOURNAL_CATEGORIES => 'Категории журнала',
            self::TYPE_COMFORT_LIST => 'Блоки комфорта',
            self::TYPE_STACK_LIST => 'Блоки стека',
            self::TYPE_STACK_SECTION => 'Технологии',
            self::TYPE_ETHICS => 'Этика производства',
            self::TYPE_TITLE_TEXT => 'Заголовок и текст',
            self::TYPE_ABOUT_INTRO => 'О нас — вступление',
            self::TYPE_ABOUT_GALLERY => 'О нас — галерея',
            self::TYPE_ABOUT_TIMELINE => 'О нас — история',
            self::TYPE_VACANCIES_VALUES => 'Вакансии — вступление',
            self::TYPE_VACANCIES_GALLERY => 'Вакансии — галерея',
            self::TYPE_VACANCIES_GROUPS => 'Вакансии — блоки',
            self::TYPE_LIBRARY_IMPLEMENTED_MODELS => 'Библиотека — реализованные модели',
            self::TYPE_LIBRARY_YOUR_IDEA => 'Библиотека — ваша идея',
            self::TYPE_LIBRARY_DOCUMENTS => 'Библиотека — документы',
            self::TYPE_JSON => 'Неизвестный блок',
            self::TYPE_ARTICLE_BLOCKS => 'Блоки текста',
        ];
    }

    public static function detect(string $pageSlug, string $key, mixed $value): string
    {
        if ($key === 'seo') {
            return self::TYPE_SEO;
        }

        if ($key === 'hero' && is_array($value)) {
            if (isset($value['collectionTitle'])) {
                return self::TYPE_HERO_HOME;
            }
            if (isset($value['imageDesktop']) || isset($value['image'])) {
                return self::TYPE_HERO_MEDIA;
            }

            return self::TYPE_HERO_SIMPLE;
        }

        if ($key === 'philosophy') {
            return self::TYPE_PHILOSOPHY;
        }

        if ($key === 'intro' && is_array($value)) {
            if (isset($value['text']) && !isset($value['lead'])) {
                return self::TYPE_INTRO_TEXT;
            }

            return self::TYPE_INTRO;
        }

        if ($key === 'contact') {
            return self::TYPE_CONTACT_CTA;
        }

        if ($key === 'info') {
            return self::TYPE_INFO_CONTACTS;
        }

        if ($key === 'collections' && is_array($value)) {
            return self::TYPE_COLLECTIONS;
        }

        if ($key === 'products' && is_array($value)) {
            return self::TYPE_HOME_PRODUCTS;
        }

        if ($key === 'partners' && is_array($value) && isset($value['cards'])) {
            return self::TYPE_HOME_PARTNERS;
        }

        if ($key === 'journal' && is_array($value) && array_is_list($value)) {
            return self::TYPE_HOME_JOURNAL;
        }

        if ($key === 'mission' && is_array($value)) {
            return self::TYPE_MISSION;
        }

        if ($key === 'formats' && is_array($value)) {
            if (isset($value['items']) && !array_is_list($value)) {
                return self::TYPE_FORMATS_SECTION;
            }

            return self::TYPE_FORMATS_LIST;
        }

        if ($key === 'audience' && is_array($value)) {
            if (isset($value['items']) && !array_is_list($value)) {
                return self::TYPE_AUDIENCE_SECTION;
            }

            return self::TYPE_AUDIENCE_LIST;
        }

        if ($key === 'salonFormats' && is_array($value)) {
            return self::TYPE_SALON_FORMATS_SECTION;
        }

        if ($pageSlug === 'vacancies' && $key === 'values' && is_array($value)) {
            return self::TYPE_VACANCIES_VALUES;
        }

        if ($pageSlug === 'vacancies' && $key === 'gallery' && is_array($value)) {
            return self::TYPE_VACANCIES_GALLERY;
        }

        if ($key === 'gallery' && is_array($value)) {
            if (isset($value['text']) || isset($value['photos'])) {
                return self::TYPE_GALLERY_STACK_SECTION;
            }

            $slides = $value['slides'] ?? [];
            $first = $slides[0] ?? null;
            if (is_array($first) && isset($first['title'])) {
                return self::TYPE_GALLERY_CAPTIONED;
            }

            return self::TYPE_GALLERY_SIMPLE;
        }

        if ($key === 'terms' && is_array($value)) {
            return self::TYPE_TERMS;
        }

        if ($key === 'presentation' && is_array($value)) {
            if (isset($value['items']) || isset($value['image'])) {
                return self::TYPE_PRESENTATION_SECTION;
            }

            return self::TYPE_PRESENTATION;
        }

        if ($key === 'materials' && is_array($value)) {
            if (!array_is_list($value)) {
                return self::TYPE_MATERIALS_SECTION;
            }

            return self::TYPE_MATERIALS_LIST;
        }

        if ($key === 'samples' && is_array($value)) {
            return self::TYPE_SAMPLES;
        }

        if ($key === 'photoStack' && is_array($value)) {
            return self::TYPE_PHOTO_STACK;
        }

        if ($key === 'regions' && is_array($value)) {
            return self::TYPE_REGIONS;
        }

        if ($key === 'categories' && is_array($value)) {
            return match ($pageSlug) {
                'faq' => self::TYPE_FAQ_CATEGORIES,
                'journal' => self::TYPE_JOURNAL_CATEGORIES,
                default => self::TYPE_JSON,
            };
        }

        if ($key === 'comfort' && is_array($value)) {
            return self::TYPE_COMFORT_LIST;
        }

        if ($key === 'stack' && is_array($value)) {
            return self::TYPE_STACK_SECTION;
        }

        if ($key === 'standards' && is_array($value)) {
            return self::TYPE_TITLE_TEXT;
        }

        if ($key === 'ethics' && is_array($value)) {
            return self::TYPE_ETHICS;
        }

        if ($key === 'jobsIntro' && is_array($value)) {
            return self::TYPE_TITLE_TEXT;
        }

        if ($pageSlug === 'about' && $key === 'intro' && is_array($value) && isset($value['paragraphs'])) {
            return self::TYPE_ABOUT_INTRO;
        }

        if ($pageSlug === 'about' && $key === 'community' && is_array($value)) {
            return self::TYPE_ABOUT_GALLERY;
        }

        if ($pageSlug === 'about' && $key === 'timeline' && is_array($value)) {
            return self::TYPE_ABOUT_TIMELINE;
        }

        if (in_array($key, ['community', 'timeline', 'values'], true) && is_array($value)) {
            return self::TYPE_JSON;
        }

        if ($pageSlug === 'vacancies' && $key === 'groups' && is_array($value)) {
            return self::TYPE_VACANCIES_GROUPS;
        }

        if ($pageSlug === 'library' && $key === 'implementedModels' && is_array($value)) {
            return self::TYPE_LIBRARY_IMPLEMENTED_MODELS;
        }

        if ($pageSlug === 'library' && $key === 'yourIdea' && is_array($value)) {
            return self::TYPE_LIBRARY_YOUR_IDEA;
        }

        if ($pageSlug === 'library' && $key === 'documents' && is_array($value)) {
            return self::TYPE_LIBRARY_DOCUMENTS;
        }

        if (($pageSlug === 'privacy-policy' || $pageSlug === 'user-agreement') && $key === 'blocks' && is_array($value)) {
            return self::TYPE_ARTICLE_BLOCKS;
        }

        return self::TYPE_JSON;
    }

    public static function isTyped(string $type): bool
    {
        return $type !== self::TYPE_JSON;
    }

    public static function formPartial(string $type): string
    {
        return match ($type) {
            self::TYPE_SEO => '_block_seo',
            self::TYPE_HERO_SIMPLE => '_block_hero_simple',
            self::TYPE_HERO_MEDIA => '_block_hero_media',
            self::TYPE_HERO_HOME => '_block_hero_home',
            self::TYPE_PHILOSOPHY => '_block_philosophy',
            self::TYPE_INTRO => '_block_intro',
            self::TYPE_INTRO_TEXT => '_block_intro_text',
            self::TYPE_CONTACT_CTA => '_block_contact',
            self::TYPE_INFO_CONTACTS => '_block_info',
            self::TYPE_COLLECTIONS => '_block_collections',
            self::TYPE_HOME_PRODUCTS => '_block_home_products',
            self::TYPE_HOME_PARTNERS => '_block_home_partners',
            self::TYPE_HOME_JOURNAL => '_block_home_journal',
            self::TYPE_MISSION => '_block_mission',
            self::TYPE_FORMATS_LIST => '_block_formats',
            self::TYPE_FORMATS_SECTION => '_block_formats_section',
            self::TYPE_AUDIENCE_LIST => '_block_audience',
            self::TYPE_AUDIENCE_SECTION => '_block_audience_section',
            self::TYPE_SALON_FORMATS_SECTION => '_block_salon_formats_section',
            self::TYPE_GALLERY_SIMPLE => '_block_gallery_simple',
            self::TYPE_GALLERY_CAPTIONED => '_block_gallery_captioned',
            self::TYPE_GALLERY_STACK_SECTION => '_block_designers_gallery',
            self::TYPE_TERMS => '_block_terms',
            self::TYPE_PRESENTATION => '_block_presentation',
            self::TYPE_PRESENTATION_SECTION => '_block_presentation_section',
            self::TYPE_MATERIALS_LIST => '_block_materials',
            self::TYPE_MATERIALS_SECTION => '_block_designers_materials_section',
            self::TYPE_SAMPLES => '_block_samples',
            self::TYPE_PHOTO_STACK => '_block_photo_stack',
            self::TYPE_REGIONS => '_block_regions',
            self::TYPE_FAQ_CATEGORIES => '_block_faq_categories',
            self::TYPE_JOURNAL_CATEGORIES => '_block_journal_tabs',
            self::TYPE_COMFORT_LIST => '_block_comfort',
            self::TYPE_STACK_LIST => '_block_stack',
            self::TYPE_STACK_SECTION => '_block_stack',
            self::TYPE_ETHICS => '_block_ethics',
            self::TYPE_TITLE_TEXT => '_block_title_text',
            self::TYPE_ABOUT_INTRO => '_block_about_intro',
            self::TYPE_ABOUT_GALLERY => '_block_about_gallery',
            self::TYPE_ABOUT_TIMELINE => '_block_about_timeline',
            self::TYPE_VACANCIES_VALUES => '_block_vacancies_intro',
            self::TYPE_VACANCIES_GALLERY => '_block_vacancies_gallery',
            self::TYPE_VACANCIES_GROUPS => '_block_vacancies_groups',
            self::TYPE_LIBRARY_IMPLEMENTED_MODELS => '_block_library_implemented_models',
            self::TYPE_LIBRARY_YOUR_IDEA => '_block_library_your_idea',
            self::TYPE_LIBRARY_DOCUMENTS => '_block_library_documents',
            default => '_block_unknown',
        };
    }
}
