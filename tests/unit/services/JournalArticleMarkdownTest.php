<?php

namespace tests\unit\services;

use app\services\journal\JournalArticleBlockBuilder;
use app\services\journal\JournalArticleMarkdownParser;
use app\services\journal\JournalArticleMarkdownSerializer;
use Codeception\Test\Unit;

class JournalArticleMarkdownTest extends Unit
{
    public function testParseHeadingTextAndDivider(): void
    {
        $markdown = "## Заголовок\n\nПервый абзац.\n\n---\n\nВторой абзац.";

        $blocks = JournalArticleMarkdownParser::parse($markdown);

        $this->assertSame('heading', $blocks[0]['type']);
        $this->assertSame(2, $blocks[0]['level']);
        $this->assertSame('text', $blocks[1]['type']);
        $this->assertSame('divider', $blocks[2]['type']);
        $this->assertSame('text', $blocks[3]['type']);
    }

    public function testParseImageQuoteAndGallery(): void
    {
        $markdown = <<<MD
![alt1](/uploads/a.jpg)
*Подпись*

> Цитата

— Автор

::: gallery columns=2
![one](/uploads/1.jpg)
![two](/uploads/2.jpg)
:::
MD;

        $blocks = JournalArticleMarkdownParser::parse($markdown);

        $this->assertSame('image', $blocks[0]['type']);
        $this->assertSame('/uploads/a.jpg', $blocks[0]['image']['src']);
        $this->assertSame('Подпись', $blocks[0]['caption']);

        $this->assertSame('quote', $blocks[1]['type']);
        $this->assertSame('Цитата', $blocks[1]['text']);
        $this->assertSame('Автор', $blocks[1]['author']);

        $this->assertSame('gallery', $blocks[2]['type']);
        $this->assertSame(2, $blocks[2]['columns']);
        $this->assertCount(2, $blocks[2]['images']);
    }

    public function testRoundTripDemoArticleStructure(): void
    {
        $blocks = [
            [
                'type' => 'image',
                'image' => ['src' => 'https://example.test/preview.webp', 'alt' => 'Превью'],
                'caption' => 'Подпись к фото',
            ],
            ['type' => 'text', 'text' => 'Абзац один.'],
            ['type' => 'divider'],
            [
                'type' => 'quote',
                'text' => 'Цитата.',
                'author' => 'автор',
            ],
            [
                'type' => 'gallery',
                'columns' => 2,
                'images' => [
                    ['src' => '/uploads/1.jpg', 'alt' => 'A'],
                    ['src' => '/uploads/2.jpg', 'alt' => 'B'],
                ],
            ],
        ];

        $markdown = JournalArticleMarkdownSerializer::fromBlocks($blocks);
        $parsed = JournalArticleMarkdownParser::parse($markdown);

        $this->assertSame('image', $parsed[0]['type']);
        $this->assertSame('https://example.test/preview.webp', $parsed[0]['image']['src']);
        $this->assertSame('Подпись к фото', $parsed[0]['caption']);
        $this->assertSame('divider', $parsed[2]['type']);
        $this->assertSame('quote', $parsed[3]['type']);
        $this->assertSame('автор', $parsed[3]['author']);
        $this->assertSame('gallery', $parsed[4]['type']);
        $this->assertCount(2, $parsed[4]['images']);
    }

    public function testMarkdownLinkBecomesParagraphParts(): void
    {
        $markdown = 'См. [Контакты](/contacts).';

        $blocks = JournalArticleMarkdownParser::parse($markdown);

        $this->assertSame('text', $blocks[0]['type']);
        $this->assertArrayHasKey('paragraphs', $blocks[0]);
        $this->assertIsArray($blocks[0]['paragraphs'][0]);
    }

    public function testSerializerUsesBlockBuilderTypes(): void
    {
        $markdown = JournalArticleMarkdownSerializer::fromBlocks([
            ['type' => JournalArticleBlockBuilder::TYPE_HEADING, 'level' => 3, 'text' => 'H3'],
        ]);

        $this->assertSame('### H3', $markdown);
    }

    public function testParseImageWithVariantSuffix(): void
    {
        $blocks = JournalArticleMarkdownParser::parse('![Swatch](927#large)');

        $this->assertSame('image', $blocks[0]['type']);
        $this->assertSame('927#large', $blocks[0]['image']['src']);
        $this->assertSame('Swatch', $blocks[0]['image']['alt']);
    }
}
