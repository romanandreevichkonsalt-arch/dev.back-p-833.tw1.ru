<?php

namespace tests\unit\services;

use app\services\content\BlockFormBuilders;
use Codeception\Test\Unit;

class FaqAnswerMarkdownTest extends Unit
{
    public function testPlainParagraphsStayStrings(): void
    {
        $result = BlockFormBuilders::faqAnswerMarkdownToParagraphs("Первый абзац.\n\nВторой абзац.");

        $this->assertSame(['Первый абзац.', 'Второй абзац.'], $result);
    }

    public function testMarkdownLinkBecomesRichParagraph(): void
    {
        $result = BlockFormBuilders::faqAnswerMarkdownToParagraphs(
            'Текст на странице «[Контакты](/contacts)».'
        );

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
        $this->assertSame([
            ['t' => 'Текст на странице «'],
            ['t' => 'Контакты', 'to' => '/contacts', 's' => 'link'],
            ['t' => '».'],
        ], $result[0]);
    }

    public function testRoundTripToForm(): void
    {
        $paragraphs = [
            'Простой абзац.',
            [
                ['t' => 'См. '],
                ['t' => 'Контакты', 'to' => '/contacts', 's' => 'link'],
                ['t' => '.'],
            ],
        ];

        $form = BlockFormBuilders::faqAnswerToForm($paragraphs);
        $parsed = BlockFormBuilders::faqAnswerMarkdownToParagraphs($form);

        $this->assertSame($paragraphs, $parsed);
    }

    public function testFromItemPostUsesMarkdownAnswer(): void
    {
        $paragraphs = BlockFormBuilders::faqAnswerFromItemPost([
            'question' => 'Вопрос',
            'answer' => 'Перейдите в [Контакты](/contacts).',
        ]);

        $this->assertSame([
            [
                ['t' => 'Перейдите в '],
                ['t' => 'Контакты', 'to' => '/contacts', 's' => 'link'],
                ['t' => '.'],
            ],
        ], $paragraphs);
    }
}
