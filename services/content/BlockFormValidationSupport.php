<?php

namespace app\services\content;

use app\modules\admin\helpers\BlockFormPostHelper;

class BlockFormValidationSupport
{
    /**
     * @param array<string, mixed> $row
     */
    public static function hasImage(array $row): bool
    {
        return BlockFormPostHelper::imageFromRow($row) !== null;
    }

    /**
     * @param array<int, mixed> $rows
     * @return string[]
     */
    public static function validateGalleryRows(array $rows, string $itemLabel): array
    {
        $errors = [];
        $index = 0;

        foreach ($rows as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            if (self::hasImage($row)) {
                continue;
            }

            $alt = trim((string)($row['image_alt'] ?? ''));
            $src = trim((string)($row['image_src'] ?? ''));
            if ($alt !== '' || $src !== '') {
                $errors[] = "{$itemLabel} {$index}: загрузите изображение — строка без фото не сохранится.";
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $rows
     * @return string[]
     */
    public static function validateRequiredTextRows(array $rows, string $itemLabel, string $field = 'text'): array
    {
        $errors = [];
        $index = 0;

        foreach ($rows as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $number = trim((string)($row['number'] ?? ''));
            $text = trim((string)($row[$field] ?? ''));
            if ($text === '' && $number !== '') {
                $errors[] = "{$itemLabel} {$index}: введите текст — строка с номером без текста не сохранится.";
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $rows
     * @return string[]
     */
    public static function validateTitleTextRows(array $rows, string $itemLabel): array
    {
        $errors = [];
        $index = 0;

        foreach ($rows as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string)($row['title'] ?? ''));
            $text = trim((string)($row['text'] ?? ($row['description'] ?? '')));
            if ($title === '' && $text === '') {
                continue;
            }

            if ($title === '' || $text === '') {
                $errors[] = "{$itemLabel} {$index}: заполните и заголовок, и описание — частично заполненная строка сохранится не полностью.";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function validateTitleAndImageBothRequired(array $row, string $label): ?string
    {
        $title = trim((string)($row['title'] ?? ''));
        $image = BlockFormPostHelper::imageFromRow($row);

        if ($title === '' && $image === null) {
            return null;
        }

        if ($title === '') {
            return "{$label}: укажите заголовок — карточка без заголовка не сохранится.";
        }

        if ($image === null) {
            return "{$label}: загрузите фото — карточка без изображения не сохранится.";
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function validateTitleOrImageRow(array $row, string $label): ?string
    {
        $title = trim((string)($row['title'] ?? ''));
        $description = trim((string)($row['description'] ?? ($row['text'] ?? '')));
        $image = BlockFormPostHelper::imageFromRow($row);

        if ($title === '' && $description === '' && $image === null) {
            return null;
        }

        if ($title === '' && $image === null) {
            return "{$label}: заполните заголовок или загрузите фото — полностью пустая строка не сохранится.";
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function validateLabelValuePair(array $row, string $label): ?string
    {
        $left = trim((string)($row['label'] ?? ''));
        $right = trim((string)($row['value'] ?? ''));
        if ($left === '' && $right === '') {
            return null;
        }

        if ($left === '' || $right === '') {
            return "{$label}: заполните и название, и значение — частично заполненное условие не сохранится.";
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function validateCoordinates(array $row, string $label): ?string
    {
        $lon = trim((string)($row['lon'] ?? ''));
        $lat = trim((string)($row['lat'] ?? ''));
        if ($lon === '' && $lat === '') {
            return null;
        }

        if ($lon === '' || $lat === '') {
            return "{$label}: укажите и долготу, и широту — иначе точка на карте не появится.";
        }

        if (!is_numeric($lon) || !is_numeric($lat)) {
            return "{$label}: координаты должны быть числами.";
        }

        return null;
    }

    /**
     * @param array<int, mixed> $slideRows
     */
    public static function countCollectionSlideAttempts(array $slideRows): int
    {
        $count = 0;

        foreach ($slideRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            $src = trim((string)($row['image_src'] ?? ''));

            if ($label !== '' || $image !== null || $src !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, mixed> $slideRows
     * @return string[]
     */
    public static function validateCollectionSlides(array $slideRows, int $cardNumber): array
    {
        $errors = [];
        $index = 0;

        foreach ($slideRows as $row) {
            $index++;
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            $image = BlockFormPostHelper::imageFromRow($row);
            if ($label !== '' && $image === null) {
                $errors[] = "Карточка {$cardNumber}, фото {$index}: загрузите изображение — подпись без фото не сохранится.";
            }
        }

        return $errors;
    }
}
