<?php

namespace app\services\journal;

use app\modules\admin\helpers\BlockFormPostHelper;
use app\services\content\BlockFormBuilders;

class JournalArticleBlockBuilder
{
    public const TYPE_TEXT = 'text';
    public const TYPE_HEADING = 'heading';
    public const TYPE_IMAGE = 'image';
    public const TYPE_QUOTE = 'quote';
    public const TYPE_DIVIDER = 'divider';
    public const TYPE_GALLERY = 'gallery';

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_TEXT => 'Текст',
            self::TYPE_HEADING => 'Заголовок',
            self::TYPE_IMAGE => 'Фото',
            self::TYPE_QUOTE => 'Цитата',
            self::TYPE_DIVIDER => 'Разделитель',
            self::TYPE_GALLERY => 'Галерея',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int, array<string, mixed>>
     */
    public static function blocksFromPost(array $post): array
    {
        $result = [];

        foreach (BlockFormPostHelper::rows($post['blocks'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = trim((string)($row['type'] ?? ''));
            $block = self::blockFromRow($type, $row);
            if ($block !== null) {
                $result[] = $block;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function blockFromRow(string $type, array $row): ?array
    {
        switch ($type) {
            case self::TYPE_TEXT:
                $block = self::richTextBlockFromPost(self::TYPE_TEXT, $row);
                return $block;

            case self::TYPE_HEADING:
                $text = trim((string)($row['text'] ?? ''));
                if ($text === '') {
                    return null;
                }

                $level = (int)($row['level'] ?? 2);
                if ($level < 2 || $level > 3) {
                    $level = 2;
                }

                return ['type' => self::TYPE_HEADING, 'level' => $level, 'text' => $text];

            case self::TYPE_IMAGE:
                $image = BlockFormPostHelper::imageFromRow($row);
                if ($image === null) {
                    return null;
                }

                $block = ['type' => self::TYPE_IMAGE, 'image' => $image];
                $caption = trim((string)($row['caption'] ?? ''));
                if ($caption !== '') {
                    $block['caption'] = $caption;
                }

                return $block;

            case self::TYPE_QUOTE:
                $block = self::richTextBlockFromPost(self::TYPE_QUOTE, $row);
                if ($block === null) {
                    return null;
                }

                $author = trim((string)($row['author'] ?? ''));
                if ($author !== '') {
                    $block['author'] = $author;
                }

                return $block;

            case self::TYPE_DIVIDER:
                return ['type' => self::TYPE_DIVIDER];

            case self::TYPE_GALLERY:
                $images = [];
                foreach (BlockFormPostHelper::rows($row['images'] ?? null) as $imageRow) {
                    if (!is_array($imageRow)) {
                        continue;
                    }

                    $image = BlockFormPostHelper::imageFromRow($imageRow);
                    if ($image !== null) {
                        $images[] = $image;
                    }
                }

                if ($images === []) {
                    return null;
                }

                $columns = (int)($row['columns'] ?? 2);
                if ($columns < 2 || $columns > 3) {
                    $columns = 2;
                }

                return [
                    'type' => self::TYPE_GALLERY,
                    'columns' => $columns,
                    'images' => $images,
                ];

            default:
                return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function blocksToForm(array $blocks): array
    {
        $result = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = trim((string)($block['type'] ?? ''));
            $formRow = self::blockToFormRow($type, $block);
            if ($formRow !== null) {
                $result[] = $formRow;
            }
        }

        if ($result === []) {
            $result[] = self::emptyFormRow(self::TYPE_TEXT);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function blockToFormRow(string $type, array $block): ?array
    {
        switch ($type) {
            case self::TYPE_TEXT:
                return [
                    'type' => self::TYPE_TEXT,
                    'text' => self::richTextToForm($block),
                ];

            case self::TYPE_HEADING:
                return [
                    'type' => self::TYPE_HEADING,
                    'level' => $block['level'] ?? 2,
                    'text' => $block['text'] ?? '',
                ];

            case self::TYPE_IMAGE:
                $image = $block['image'] ?? [];

                return [
                    'type' => self::TYPE_IMAGE,
                    'image_src' => is_array($image) ? ($image['src'] ?? '') : '',
                    'image_alt' => is_array($image) ? ($image['alt'] ?? '') : '',
                    'caption' => $block['caption'] ?? '',
                ];

            case self::TYPE_QUOTE:
                return [
                    'type' => self::TYPE_QUOTE,
                    'text' => self::richTextToForm($block),
                    'author' => $block['author'] ?? '',
                ];

            case self::TYPE_DIVIDER:
                return ['type' => self::TYPE_DIVIDER];

            case self::TYPE_GALLERY:
                $images = [];
                foreach (BlockFormPostHelper::rows($block['images'] ?? null) as $image) {
                    if (!is_array($image)) {
                        continue;
                    }

                    $images[] = [
                        'image_src' => $image['src'] ?? '',
                        'image_alt' => $image['alt'] ?? '',
                    ];
                }

                if ($images === []) {
                    $images[] = ['image_src' => '', 'image_alt' => ''];
                }

                return [
                    'type' => self::TYPE_GALLERY,
                    'columns' => $block['columns'] ?? 2,
                    'images' => $images,
                ];

            default:
                return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyFormRow(string $type): array
    {
        switch ($type) {
            case self::TYPE_HEADING:
                return ['type' => self::TYPE_HEADING, 'level' => 2, 'text' => ''];

            case self::TYPE_IMAGE:
                return ['type' => self::TYPE_IMAGE, 'image_src' => '', 'image_alt' => '', 'caption' => ''];

            case self::TYPE_QUOTE:
                return ['type' => self::TYPE_QUOTE, 'text' => '', 'author' => ''];

            case self::TYPE_DIVIDER:
                return ['type' => self::TYPE_DIVIDER];

            case self::TYPE_GALLERY:
                return [
                    'type' => self::TYPE_GALLERY,
                    'columns' => 2,
                    'images' => [['image_src' => '', 'image_alt' => '']],
                ];

            default:
                return ['type' => self::TYPE_TEXT, 'text' => ''];
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function richTextBlockFromPost(string $type, array $row): ?array
    {
        $markdown = trim((string)($row['text'] ?? ''));
        if ($markdown === '') {
            return null;
        }

        $paragraphs = BlockFormBuilders::faqAnswerMarkdownToParagraphs($markdown);
        if ($paragraphs === []) {
            return null;
        }

        $block = ['type' => $type];
        if (count($paragraphs) === 1 && is_string($paragraphs[0])) {
            $block['text'] = $paragraphs[0];

            return $block;
        }

        $block['paragraphs'] = $paragraphs;

        return $block;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function richTextToForm(array $block): string
    {
        $paragraphs = $block['paragraphs'] ?? null;
        if (is_array($paragraphs) && $paragraphs !== []) {
            return BlockFormBuilders::faqAnswerToForm($paragraphs);
        }

        return (string)($block['text'] ?? '');
    }
}
