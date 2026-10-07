<?php

namespace app\services\journal;

use app\services\content\BlockFormBuilders;

class JournalArticleMarkdownSerializer
{
    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public static function fromBlocks(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $chunk = self::blockToMarkdown($block);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function blockToMarkdown(array $block): string
    {
        $type = trim((string)($block['type'] ?? ''));

        switch ($type) {
            case JournalArticleBlockBuilder::TYPE_TEXT:
            case JournalArticleBlockBuilder::TYPE_QUOTE:
                return self::richTextToMarkdown($block, $type);

            case JournalArticleBlockBuilder::TYPE_HEADING:
                $level = (int)($block['level'] ?? 2);
                $prefix = $level === 3 ? '### ' : '## ';
                $text = trim((string)($block['text'] ?? ''));

                return $text === '' ? '' : $prefix . $text;

            case JournalArticleBlockBuilder::TYPE_IMAGE:
                return self::imageToMarkdown($block);

            case JournalArticleBlockBuilder::TYPE_DIVIDER:
                return '---';

            case JournalArticleBlockBuilder::TYPE_GALLERY:
                return self::galleryToMarkdown($block);

            default:
                return '';
        }
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function richTextToMarkdown(array $block, string $type): string
    {
        $body = self::richTextBody($block);
        if ($body === '') {
            return '';
        }

        if ($type === JournalArticleBlockBuilder::TYPE_QUOTE) {
            $lines = array_map(
                static fn (string $line): string => '> ' . $line,
                explode("\n", $body)
            );
            $markdown = implode("\n", $lines);
            $author = trim((string)($block['author'] ?? ''));
            if ($author !== '') {
                $markdown .= "\n\n— " . $author;
            }

            return $markdown;
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function richTextBody(array $block): string
    {
        $paragraphs = $block['paragraphs'] ?? null;
        if (is_array($paragraphs) && $paragraphs !== []) {
            return BlockFormBuilders::faqAnswerToForm($paragraphs);
        }

        return trim((string)($block['text'] ?? ''));
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function imageToMarkdown(array $block): string
    {
        $image = $block['image'] ?? [];
        if (!is_array($image)) {
            return '';
        }

        $src = trim((string)($image['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $alt = (string)($image['alt'] ?? '');
        $lines = ['![' . self::escapeAlt($alt) . '](' . $src . ')'];
        $caption = trim((string)($block['caption'] ?? ''));
        if ($caption !== '') {
            $lines[] = '*' . $caption . '*';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function galleryToMarkdown(array $block): string
    {
        $images = $block['images'] ?? [];
        if (!is_array($images) || $images === []) {
            return '';
        }

        $columns = (int)($block['columns'] ?? 2);
        if ($columns < 2 || $columns > 3) {
            $columns = 2;
        }

        $lines = ['::: gallery columns=' . $columns];
        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }

            $src = trim((string)($image['src'] ?? ''));
            if ($src === '') {
                continue;
            }

            $alt = (string)($image['alt'] ?? '');
            $lines[] = '![' . self::escapeAlt($alt) . '](' . $src . ')';
        }

        if (count($lines) === 1) {
            return '';
        }

        $lines[] = ':::';

        return implode("\n", $lines);
    }

    private static function escapeAlt(string $alt): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $alt);
    }
}
