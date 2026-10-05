<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;
use app\services\content\BlockTypeRegistry;

class ContentBlockUi
{
    /**
     * @return array<string, string>
     */
    public static function keyLabels(): array
    {
        return [
            'seo' => 'Поиск в Google (SEO)',
            'hero' => 'Главный баннер',
            'philosophy' => 'Философия бренда',
            'intro' => 'Вступление',
            'collections' => 'Коллекции',
            'products' => 'Товары',
            'partners' => 'Текст и карточки',
            'journal' => 'Журнал',
            'mission' => 'Миссия и ценности',
            'formats' => 'Форматы партнёрства',
            'salonFormats' => 'Форматы салона',
            'audience' => 'Целевая аудитория',
            'gallery' => 'Галерея фото',
            'contact' => 'Блок с формой',
            'info' => 'Контакты и форма',
            'faq' => 'Вопросы и ответы',
            'regions' => 'Регионы',
            'terms' => 'Условия',
            'presentation' => 'Презентация',
            'materials' => 'Материалы',
            'samples' => 'Образцы',
            'photoStack' => 'Стопка фото',
            'categories' => 'Категории',
            'comfort' => 'Комфорт',
            'stack' => 'Технологии',
            'standards' => 'Этика и стандарты',
            'ethics' => 'Этика',
        ];
    }

    public static function keyLabel(string $key): string
    {
        return self::keyLabels()[$key] ?? $key;
    }

    public static function keyHint(string $key, ?string $pageSlug = null): string
    {
        return match ($key) {
            'seo' => match ($pageSlug) {
                'partners', 'designers', 'contacts', 'faq', 'journal', 'building' => 'Редактируется в блоке «Главный баннер».',
                default => $pageSlug === 'home'
                    ? 'Редактируется в блоке «Главный баннер».'
                    : 'Заголовок и описание страницы для поисковиков и вкладки браузера.',
            },
            'hero' => match ($pageSlug) {
                'home' => 'SEO, фото баннера (компьютер и телефон), тексты на главной.',
                'partners', 'designers', 'contacts', 'faq', 'journal', 'building' => 'SEO, фото для компьютера и телефона, заголовок и подзаголовок.',
                default => 'Большой баннер вверху страницы: заголовки и фото.',
            },
            'philosophy' => $pageSlug === 'home'
                ? 'Редактируется в блоке «Коллекции».'
                : 'Короткий текст о философии бренда.',
            'intro' => match ($pageSlug) {
                'partners' => 'Текст, фото слева и 5 слайдов преимуществ справа.',
                'designers' => 'Текст, фото слева и 4 слайда преимуществ справа.',
                'faq' => 'Заголовок «FAQs» и подзаголовок под баннером.',
                default => 'Вводный абзац после баннера.',
            },
            'mission' => match ($pageSlug) {
                'partners' => 'Редактируется в блоке «О бренде».',
                'designers' => 'Редактируется в блоке «Вступление».',
                default => 'Карточки с иконками и текстом миссии.',
            },
            'formats' => $pageSlug === 'partners'
                ? 'Тексты, 4 пункта «Вы получите» и фото справа.'
                : 'Форматы открытия: салон, шоурум — фото, заголовок, текст и id для API.',
            'salonFormats' => $pageSlug === 'partners'
                ? 'Три формата салона и общие условия партнёрства.'
                : 'Форматы салона для партнёров.',
            'audience' => $pageSlug === 'partners'
                ? 'Баннер, 3 карточки и показатели внизу.'
                : 'Кому подходит партнёрская программа (без фото).',
            'gallery' => $pageSlug === 'designers'
                ? 'Текст и стопка фото в центре блока.'
                : ($pageSlug === 'partners'
                    ? 'Фото салонов (слайдер).'
                    : 'Слайдер фотографий.'),
            'terms' => $pageSlug === 'partners'
                ? 'Условия входа: инвестиции, сроки, требования.'
                : 'Условия партнёрской программы.',
            'presentation' => $pageSlug === 'partners'
                ? 'Список преимуществ, PDF и фото справа.'
                : 'Ссылка на PDF-презентацию.',
            'contact' => match ($pageSlug) {
                'contacts' => 'Фото, заголовки формы и PDF политики конфиденциальности.',
                default => 'Редактируется на странице «Контакты».',
            },
            'materials' => $pageSlug === 'designers'
                ? 'Фото на фоне, текст, архив и три колонки с описанием.'
                : 'Список материалов и инструментов для дизайнеров.',
            'samples' => 'Блок про образцы материалов.',
            'photoStack' => 'Декоративная стопка фотографий.',
            'categories' => match ($pageSlug) {
                'faq' => 'Четыре вкладки с вопросами и ответами.',
                'journal' => 'Четыре вкладки категорий статей.',
                default => 'Категории с вопросами или статьями.',
            },
            'comfort' => 'Блоки о комфорте на странице производства.',
            'stack' => 'Этика и стандарты, затем блоки о технологиях и экологии.',
            'ethics' => 'Текст и фото об этике производства.',
            'partners' => 'Вводный текст и две карточки с фото, заголовками и ссылками.',
            'journal' => $pageSlug === 'home'
                ? 'Три последние статьи подставляются автоматически из страницы «Журнал».'
                : 'Превью статей журнала.',
            'collections' => $pageSlug === 'home'
                ? 'Философия бренда и две карточки коллекций с фото.'
                : 'Две карточки: коллекция из настроек и фото.',
            'products' => 'Карточки товаров на главной.',
            'info' => 'Телефон, email, адрес и ссылки на мессенджеры. Подписи формы — в блоке «Форма заявки».',
            'faq' => 'Список вопросов и ответов.',
            'regions' => 'Карта или список регионов.',
            default => 'Содержимое блока для API страницы.',
        };
    }

    public static function preview(ContentBlock $block): string
    {
        $data = $block->getDataArray();
        $type = $block->block_type;

        if ($type === BlockTypeRegistry::TYPE_SEO) {
            return (string)($data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_HERO_SIMPLE) {
            return (string)($data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_HERO_MEDIA || $type === BlockTypeRegistry::TYPE_HERO_HOME) {
            return (string)($data['collectionTitle'] ?? $data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_PHILOSOPHY) {
            return mb_substr((string)($data['text'] ?? ''), 0, 120);
        }
        if ($type === BlockTypeRegistry::TYPE_INTRO) {
            return (string)($data['lead'] ?? $data['text'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_CONTACT_CTA) {
            return (string)($data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_INFO_CONTACTS) {
            return (string)($data['phone'] ?? $data['email'] ?? '');
        }

        if ($type === BlockTypeRegistry::TYPE_COLLECTIONS && is_array($data)) {
            return count($data) . ' коллекций';
        }
        if ($type === BlockTypeRegistry::TYPE_HOME_PRODUCTS && is_array($data)) {
            return count($data) . ' товаров';
        }
        if ($type === BlockTypeRegistry::TYPE_HOME_JOURNAL && is_array($data)) {
            return count($data) . ' статей';
        }
        if ($type === BlockTypeRegistry::TYPE_REGIONS && is_array($data)) {
            return count($data) . ' регионов';
        }
        if ($type === BlockTypeRegistry::TYPE_FAQ_CATEGORIES || $type === BlockTypeRegistry::TYPE_JOURNAL_CATEGORIES) {
            $categories = is_array($data) ? $data : ($data['categories'] ?? []);
            if (!is_array($categories)) {
                return '';
            }

            return count($categories) . ' категорий';
        }
        if ($type === BlockTypeRegistry::TYPE_PRESENTATION) {
            return (string)($data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_STACK_SECTION && is_array($data)) {
            $items = $data['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            return count($items) . ' блоков';
        }
        if ($type === BlockTypeRegistry::TYPE_ETHICS || $type === BlockTypeRegistry::TYPE_SAMPLES) {
            return (string)($data['title'] ?? '');
        }
        if ($type === BlockTypeRegistry::TYPE_TITLE_TEXT) {
            return (string)($data['title'] ?? '');
        }

        if (is_array($data)) {
            if (isset($data['items']) && is_array($data['items'])) {
                return count($data['items']) . ' элементов';
            }
            if (isset($data['slides']) && is_array($data['slides'])) {
                return count($data['slides']) . ' слайдов';
            }
            if (isset($data['cards']) && is_array($data['cards'])) {
                return count($data['cards']) . ' карточек';
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public static function pageDescriptions(): array
    {
        return [
            'home' => 'Главная страница сайта',
            'partners' => 'Страница для партнёров и франшизы',
            'designers' => 'Страница для дизайнеров',
            'contacts' => 'Контакты и форма',
            'faq' => 'Частые вопросы',
            'journal' => 'Журнал / блог',
            'building' => 'Производство',
            'about' => 'О компании, история и тизер вакансий',
            'vacancies' => 'Листинг вакансий по отделам',
            'library' => 'Библиотека тканей и цветов',
            'privacy-policy' => 'Текст политики конфиденциальности для сайта',
            'user-agreement' => 'Текст пользовательского соглашения для сайта',
        ];
    }

    public static function pageDescription(string $slug): string
    {
        return self::pageDescriptions()[$slug] ?? 'Контент страницы для API';
    }
}
