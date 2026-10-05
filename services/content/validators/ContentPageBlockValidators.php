<?php

namespace app\services\content\validators;

use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\modules\admin\helpers\BlockFormPostHelper;
use app\modules\admin\helpers\ContentPageVacanciesHelper;
use app\modules\admin\helpers\HomePageCollectionsHelper;
use app\modules\admin\helpers\HomePagePartnersHelper;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\content\BlockFormValidationSupport;

final class ContentPageBlockValidators
{
    /**
     * @return string[]
     */
    public static function home(string $tab, array $post): array
    {
        return match ($tab) {
            'collections' => self::homeCollections($post),
            'products' => self::homeProducts($post),
            'partners' => self::homePartners($post),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public static function partners(string $tab, array $post): array
    {
        return match ($tab) {
            'formats' => BlockFormValidationSupport::validateRequiredTextRows(
                BlockFormPostHelper::rows($post['items'] ?? null),
                'Пункт «Вы получите»'
            ),
            'audience' => array_merge(
                BlockFormValidationSupport::validateTitleTextRows(
                    BlockFormPostHelper::rows($post['items'] ?? null),
                    'Карточка'
                ),
                BlockFormValidationSupport::validateRequiredTextRows(
                    BlockFormPostHelper::rows($post['stats'] ?? null),
                    'Показатель'
                ),
            ),
            'salonFormats' => self::partnersSalonFormats($post),
            'presentation' => BlockFormValidationSupport::validateRequiredTextRows(
                BlockFormPostHelper::rows($post['items'] ?? null),
                'Преимущество'
            ),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public static function designers(string $tab, array $post): array
    {
        return match ($tab) {
            'materials' => BlockFormValidationSupport::validateTitleTextRows(
                BlockFormPostHelper::rows($post['items'] ?? null),
                'Колонка'
            ),
            'gallery' => BlockFormValidationSupport::validateGalleryRows(
                BlockFormPostHelper::rows($post['photos'] ?? null),
                'Фото'
            ),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public static function contacts(string $tab, array $post): array
    {
        if ($tab !== 'regions') {
            return [];
        }

        $errors = [];
        $regions = BlockFormPostHelper::rows($post['regions'] ?? null);

        foreach ($regions as $ri => $regionRow) {
            if (!is_array($regionRow)) {
                continue;
            }

            $regionNumber = $ri + 1;
            $name = trim((string)($regionRow['name'] ?? ''));
            $id = trim((string)($regionRow['id'] ?? ''));
            if ($name === '' && $id === '') {
                continue;
            }

            if ($name === '' || $id === '') {
                $errors[] = "Регион {$regionNumber}: укажите название и идентификатор (slug) — частично заполненный регион не сохранится.";
            }

            foreach (BlockFormPostHelper::rows($regionRow['stores'] ?? null) as $si => $storeRow) {
                if (!is_array($storeRow)) {
                    continue;
                }

                $storeNumber = $si + 1;
                $storeName = trim((string)($storeRow['name'] ?? ''));
                $address = trim((string)($storeRow['address'] ?? ''));
                if ($storeName === '' && $address === '') {
                    continue;
                }

                if ($storeName === '' || $address === '') {
                    $errors[] = "Регион {$regionNumber}, салон {$storeNumber}: укажите название и адрес — частично заполненный салон не сохранится.";
                }

                $coordError = BlockFormValidationSupport::validateCoordinates(
                    $storeRow,
                    "Регион {$regionNumber}, салон {$storeNumber}"
                );
                if ($coordError !== null) {
                    $errors[] = $coordError;
                }
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public static function faq(string $tab, array $post): array
    {
        if ($tab !== 'categories') {
            return [];
        }

        $errors = [];
        foreach (BlockFormPostHelper::rows($post['categories'] ?? null) as $ci => $catRow) {
            if (!is_array($catRow)) {
                continue;
            }

            $categoryNumber = $ci + 1;
            $label = trim((string)($catRow['label'] ?? ''));
            $id = trim((string)($catRow['id'] ?? ''));
            if ($label === '' && $id === '') {
                continue;
            }

            if ($label === '' || $id === '') {
                $errors[] = "Категория {$categoryNumber}: укажите название и идентификатор вкладки.";
            }

            foreach (BlockFormPostHelper::rows($catRow['items'] ?? null) as $qi => $itemRow) {
                if (!is_array($itemRow)) {
                    continue;
                }

                $questionNumber = $qi + 1;
                $question = trim((string)($itemRow['question'] ?? ''));
                $answer = trim((string)($itemRow['answer'] ?? ''));
                if ($question === '' && $answer === '') {
                    continue;
                }

                if ($question === '') {
                    $errors[] = "Категория {$categoryNumber}, вопрос {$questionNumber}: введите текст вопроса.";
                    continue;
                }

                if ($answer === '') {
                    $errors[] = "Категория {$categoryNumber}, вопрос {$questionNumber}: введите текст ответа.";
                }
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public static function journal(string $tab, array $post): array
    {
        if ($tab !== 'categories') {
            return [];
        }

        $errors = [];
        foreach (BlockFormPostHelper::rows($post['categories'] ?? null) as $ci => $catRow) {
            if (!is_array($catRow)) {
                continue;
            }

            $categoryNumber = $ci + 1;
            $label = trim((string)($catRow['label'] ?? ''));
            $id = trim((string)($catRow['id'] ?? ''));
            if ($label === '' && $id === '') {
                continue;
            }

            if ($label === '' || $id === '') {
                $errors[] = "Вкладка {$categoryNumber}: укажите название и идентификатор — частично заполненная вкладка не сохранится.";
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public static function building(string $tab, array $post): array
    {
        return match ($tab) {
            'comfort' => self::titleImageItems(
                BlockFormPostHelper::rows($post['items'] ?? null),
                'Блок'
            ),
            'stack' => self::titleImageItems(
                BlockFormPostHelper::rows($post['items'] ?? null),
                'Технологический блок'
            ),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public static function about(string $tab, array $post): array
    {
        return match ($tab) {
            'community' => BlockFormValidationSupport::validateGalleryRows(
                BlockFormPostHelper::rows($post['cards'] ?? null),
                'Фото'
            ),
            'timeline' => self::aboutTimeline($post),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public static function vacancies(string $tab, array $post): array
    {
        return match ($tab) {
            'values' => self::vacanciesValues($post),
            'gallery' => BlockFormValidationSupport::validateGalleryRows(
                BlockFormPostHelper::rows($post['slides'] ?? null),
                'Фото'
            ),
            'groups' => self::vacanciesGroups($post),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    private static function homeCollections(array $post): array
    {
        $errors = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageCollectionsHelper::CARD_COUNT; $i++) {
            $cardNumber = $i + 1;
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $directionId = (int)($row['catalog_direction_id'] ?? 0);
            $slideRows = BlockFormPostHelper::rows($row['slides'] ?? null);
            $filledSlides = BlockFormValidationSupport::countCollectionSlideAttempts($slideRows);

            $errors = array_merge(
                $errors,
                BlockFormValidationSupport::validateCollectionSlides($slideRows, $cardNumber)
            );

            $savedSlides = 0;
            foreach ($slideRows as $slideRow) {
                if (is_array($slideRow) && BlockFormPostHelper::imageFromRow($slideRow) !== null) {
                    $savedSlides++;
                }
            }

            if ($filledSlides > 0 && $directionId <= 0) {
                $errors[] = "Карточка {$cardNumber}: выберите направление из списка — без него фото не сохранятся.";
                continue;
            }

            if ($directionId <= 0) {
                continue;
            }

            if ($savedSlides > 0 && CatalogDirection::find()->where(['id' => $directionId])->one() === null) {
                $errors[] = "Карточка {$cardNumber}: выбранное направление не найдено в каталоге.";
                continue;
            }

            if ($savedSlides === 0 && $filledSlides > 0) {
                continue;
            }

            if ($savedSlides === 0) {
                $errors[] = "Карточка {$cardNumber}: добавьте хотя бы одно фото — карточка без фото не попадёт на сайт.";
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function homeProducts(array $post): array
    {
        $errors = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePageProductsHelper::CARD_COUNT; $i++) {
            $cardNumber = $i + 1;
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $productId = (int)($row['catalog_product_id'] ?? 0);
            $searchText = trim((string)($row['product_search'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);

            if ($searchText !== '' && $productId <= 0) {
                $errors[] = "Карточка {$cardNumber}: выберите товар из списка поиска — текст в поле сам по себе не сохраняется.";
                continue;
            }

            if ($productId <= 0 && $image === null) {
                continue;
            }

            if ($productId <= 0) {
                $errors[] = "Карточка {$cardNumber}: выберите товар из каталога — баннер без товара не сохранится.";
                continue;
            }

            if ($image === null) {
                $errors[] = "Карточка {$cardNumber}: загрузите баннер карточки.";
                continue;
            }

            if (CatalogProduct::find()->where(['id' => $productId])->one() === null) {
                $errors[] = "Карточка {$cardNumber}: выбранный товар не найден в каталоге — выберите его заново.";
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function homePartners(array $post): array
    {
        $errors = [];
        $rows = BlockFormPostHelper::rows($post['cards'] ?? null);

        for ($i = 0; $i < HomePagePartnersHelper::CARD_COUNT; $i++) {
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $error = BlockFormValidationSupport::validateTitleAndImageBothRequired(
                $row,
                'Карточка ' . ($i + 1)
            );
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function partnersSalonFormats(array $post): array
    {
        $errors = [];
        $index = 0;

        foreach (BlockFormPostHelper::rows($post['items'] ?? null) as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            $area = trim((string)($row['area'] ?? ''));
            $hasDetails = $text !== '' || $area !== '' || trim((string)($row['assortment'] ?? '')) !== ''
                || trim((string)($row['profit'] ?? '')) !== '' || trim((string)($row['employees'] ?? '')) !== ''
                || trim((string)($row['investment'] ?? '')) !== '';

            if ($title === '' && !$hasDetails) {
                continue;
            }

            if ($title === '') {
                $errors[] = "Формат {$index}: укажите название — формат без названия не сохранится.";
            }
        }

        $conditionIndex = 0;
        foreach (BlockFormPostHelper::rows($post['conditions'] ?? null) as $row) {
            $conditionIndex++;
            if (!is_array($row)) {
                continue;
            }

            $error = BlockFormValidationSupport::validateLabelValuePair(
                $row,
                "Условие {$conditionIndex}"
            );
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $rows
     * @return string[]
     */
    private static function titleImageItems(array $rows, string $label): array
    {
        $errors = [];
        $index = 0;

        foreach ($rows as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $error = BlockFormValidationSupport::validateTitleOrImageRow($row, "{$label} {$index}");
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function aboutTimeline(array $post): array
    {
        $errors = [];
        $index = 0;

        foreach (BlockFormPostHelper::rows($post['stages'] ?? null) as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $year = trim((string)($row['year'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ''));
            $images = 0;

            foreach (BlockFormPostHelper::rows($row['images'] ?? null) as $imageRow) {
                if (is_array($imageRow) && BlockFormPostHelper::imageFromRow($imageRow) !== null) {
                    $images++;
                }
            }

            if ($year === '' && $title === '' && $text === '' && $images === 0) {
                continue;
            }

            if ($images === 0 && ($year !== '' || $title !== '' || $text !== '')) {
                $errors[] = "Этап {$index}: добавьте хотя бы одно фото — этап без фото сохранится без галереи.";
            }

            foreach (BlockFormPostHelper::rows($row['images'] ?? null) as $ji => $imageRow) {
                if (!is_array($imageRow)) {
                    continue;
                }

                $alt = trim((string)($imageRow['image_alt'] ?? ''));
                $src = trim((string)($imageRow['image_src'] ?? ''));
                if (($alt !== '' || $src !== '') && BlockFormPostHelper::imageFromRow($imageRow) === null) {
                    $errors[] = "Этап {$index}, фото " . ($ji + 1) . ": загрузите изображение.";
                }
            }
        }

        $jobsTitle = trim((string)($post['jobs_intro_title'] ?? ''));
        $jobsText = trim((string)($post['jobs_intro_text'] ?? ''));
        if (($jobsTitle !== '' && $jobsText === '') || ($jobsTitle === '' && $jobsText !== '')) {
            $errors[] = 'Блок «Создавайте вместе с нами»: заполните и заголовок, и текст.';
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function vacanciesValues(array $post): array
    {
        $errors = [];
        $title = trim((string)($post['values_title'] ?? ''));
        $hasParagraph = false;

        foreach (BlockFormPostHelper::rows($post['paragraphs'] ?? null) as $row) {
            $text = trim((string)(is_array($row) ? ($row['text'] ?? '') : $row));
            if ($text !== '') {
                $hasParagraph = true;
                break;
            }
        }

        if ($hasParagraph && $title === '') {
            $errors[] = 'Укажите заголовок блока ценностей — без него текст не сохранится отдельно от заголовка.';
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private static function vacanciesGroups(array $post): array
    {
        $errors = [];
        $hasDirection = false;

        foreach (BlockFormPostHelper::rows($post['directions'] ?? null) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $hasDirection = true;
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug !== '' && !preg_match('/^[a-z0-9-]+$/', $slug)) {
                $errors[] = 'Направление ' . ($index + 1) . ': slug может содержать только a-z, 0-9 и дефис.';
            }
        }

        if (!$hasDirection) {
            $errors[] = 'Добавьте хотя бы одно направление с названием.';
        }

        return $errors;
    }
}
