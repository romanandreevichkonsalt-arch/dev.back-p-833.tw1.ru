<?php

namespace app\services\journal;

use app\models\MediaFile;
use app\services\content\BlockFormBuilders;

class JournalArticleMarkdownParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return [];
        }

        $lines = explode("\n", $markdown);
        $blocks = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            while ($index < $count && self::isBlank($lines[$index])) {
                $index++;
            }

            if ($index >= $count) {
                break;
            }

            $line = $lines[$index];

            if (trim($line) === '---') {
                $blocks[] = ['type' => JournalArticleBlockBuilder::TYPE_DIVIDER];
                $index++;
                continue;
            }

            if (preg_match('/^::: gallery(?: columns=(2|3))?\s*$/', trim($line), $matches) === 1) {
                $columns = isset($matches[1]) ? (int)$matches[1] : 2;
                $index++;
                $images = [];
                while ($index < $count && trim($lines[$index]) !== ':::') {
                    $image = self::parseImageLine(trim($lines[$index]));
                    if ($image !== null) {
                        $images[] = $image;
                    }
                    $index++;
                }
                if ($index < $count) {
                    $index++;
                }
                if ($images !== []) {
                    $blocks[] = [
                        'type' => JournalArticleBlockBuilder::TYPE_GALLERY,
                        'columns' => $columns,
                        'images' => $images,
                    ];
                }
                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $matches) === 1) {
                $level = strlen($matches[1]) === 3 ? 3 : 2;
                $text = trim($matches[2]);
                if ($text !== '') {
                    $blocks[] = [
                        'type' => JournalArticleBlockBuilder::TYPE_HEADING,
                        'level' => $level,
                        'text' => $text,
                    ];
                }
                $index++;
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line) === 1) {
                $quoteLines = [];
                while ($index < $count && preg_match('/^>\s?(.*)$/', $lines[$index], $quoteMatch) === 1) {
                    $quoteLines[] = (string)$quoteMatch[1];
                    $index++;
                }

                while ($index < $count && self::isBlank($lines[$index])) {
                    $index++;
                }

                $author = '';
                if ($index < $count && preg_match('/^—\s*(.+)$/', trim($lines[$index]), $authorMatch) === 1) {
                    $author = trim($authorMatch[1]);
                    $index++;
                }

                $quoteMarkdown = trim(implode("\n", $quoteLines));
                $block = self::richTextBlockFromMarkdown(JournalArticleBlockBuilder::TYPE_QUOTE, $quoteMarkdown);
                if ($block !== null) {
                    if ($author !== '') {
                        $block['author'] = $author;
                    }
                    $blocks[] = $block;
                }
                continue;
            }

            $imageLine = trim($line);
            if (str_starts_with($imageLine, '![')) {
                $parsedImage = self::parseImageLine($imageLine);
                if ($parsedImage !== null) {
                    $index++;
                    $caption = '';
                    if ($index < $count && preg_match('/^\*(.+)\*$/', trim($lines[$index]), $captionMatch) === 1) {
                        $caption = trim($captionMatch[1]);
                        $index++;
                    }

                    $block = [
                        'type' => JournalArticleBlockBuilder::TYPE_IMAGE,
                        'image' => $parsedImage,
                    ];
                    if ($caption !== '') {
                        $block['caption'] = $caption;
                    }
                    $blocks[] = $block;
                    continue;
                }
            }

            $textLines = [];
            while ($index < $count) {
                if (self::isBlank($lines[$index])) {
                    $next = $index + 1;
                    while ($next < $count && self::isBlank($lines[$next])) {
                        $next++;
                    }
                    if ($next < $count && self::isBlockStarterLine($lines[$next])) {
                        break;
                    }
                } elseif (self::isBlockStarterLine($lines[$index])) {
                    break;
                }

                $textLines[] = $lines[$index];
                $index++;
            }

            $textMarkdown = trim(implode("\n", $textLines));
            $textBlock = self::richTextBlockFromMarkdown(JournalArticleBlockBuilder::TYPE_TEXT, $textMarkdown);
            if ($textBlock !== null) {
                $blocks[] = $textBlock;
            }
        }

        return $blocks;
    }

    private static function isBlank(string $line): bool
    {
        return trim($line) === '';
    }

    private static function isBlockStarterLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '---') {
            return true;
        }
        if (preg_match('/^::: gallery(?: columns=(2|3))?\s*$/', $trimmed) === 1) {
            return true;
        }
        if (preg_match('/^#{2,3}\s+/', $line) === 1) {
            return true;
        }
        if (preg_match('/^>\s?/', $line) === 1 || $line === '>') {
            return true;
        }
        if (str_starts_with($trimmed, '![')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{src: string, alt: string}|null
     */
    private static function parseImageLine(string $line): ?array
    {
        if (preg_match('/^!\[(.*)\]\(([^)]+)\)$/', $line, $matches) !== 1) {
            return null;
        }

        $src = trim($matches[2]);
        if ($src === '') {
            return null;
        }

        return [
            'src' => self::normalizeImageSrc($src),
            'alt' => str_replace(['\\[', '\\]'], ['[', ']'], (string)$matches[1]),
        ];
    }

    private static function normalizeImageSrc(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        if (ctype_digit($src) || str_starts_with($src, '/') || str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }

        $basename = basename($src);
        if ($basename === '') {
            return $src;
        }

        $media = MediaFile::find()
            ->where(['filename' => $basename])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($media === null) {
            return $src;
        }

        return (string)$media->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function richTextBlockFromMarkdown(string $type, string $markdown): ?array
    {
        $markdown = trim($markdown);
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
}
