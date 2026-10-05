<?php

namespace tests\unit\services;

use app\services\content\BlockFormBuilders;
use Codeception\Test\Unit;

class AboutPageBlockFormBuildersTest extends Unit
{
    public function testAboutIntroRoundTrip(): void
    {
        $data = [
            'paragraphs' => ['Первый абзац.', 'Второй абзац.'],
            'image' => ['src' => '/uploads/about/intro.webp', 'alt' => 'Интерьер'],
        ];

        $form = BlockFormBuilders::aboutIntroToForm($data);
        $saved = BlockFormBuilders::aboutIntroFromPost($form);

        $this->assertSame($data, $saved);
    }

    public function testAboutGalleryRoundTrip(): void
    {
        $data = [
            'title' => 'Объединяем талантливых людей',
            'cards' => [
                ['image' => ['src' => '/uploads/about/1.webp', 'alt' => 'Фото 1']],
                ['image' => ['src' => '/uploads/about/2.webp', 'alt' => 'Фото 2']],
            ],
        ];

        $form = BlockFormBuilders::aboutGalleryToForm($data);
        $saved = BlockFormBuilders::aboutGalleryFromPost($form);

        $this->assertSame($data, $saved);
    }

    public function testAboutTimelineRoundTripWithGallery(): void
    {
        $data = [
            'stages' => [
                [
                    'year' => '1995',
                    'label' => '1995',
                    'title' => 'Начало',
                    'text' => 'Семейное производство.',
                    'images' => [
                        ['src' => '/uploads/about/t1.webp', 'alt' => 'Фото 1'],
                        ['src' => '/uploads/about/t2.webp', 'alt' => 'Фото 2'],
                    ],
                    'image' => ['src' => '/uploads/about/t1.webp', 'alt' => 'Фото 1'],
                ],
            ],
        ];

        $form = BlockFormBuilders::aboutTimelineToForm($data);
        $saved = BlockFormBuilders::aboutTimelineFromPost($form);

        $this->assertSame($data, $saved);
    }

    public function testAboutTimelineMigratesLegacySingleImage(): void
    {
        $legacy = [
            'stages' => [
                [
                    'year' => '2017–2019',
                    'label' => '2017–2019',
                    'text' => 'Масштабирование.',
                    'image' => ['src' => '/uploads/about/timeline-2017.webp', 'alt' => 'Цех'],
                ],
            ],
        ];

        $form = BlockFormBuilders::aboutTimelineToForm($legacy);
        $saved = BlockFormBuilders::aboutTimelineFromPost($form);

        $this->assertSame('2017–2019', $saved['stages'][0]['year']);
        $this->assertSame('Масштабирование.', $saved['stages'][0]['text']);
        $this->assertSame(
            [['src' => '/uploads/about/timeline-2017.webp', 'alt' => 'Цех']],
            $saved['stages'][0]['images']
        );
        $this->assertSame(
            ['src' => '/uploads/about/timeline-2017.webp', 'alt' => 'Цех'],
            $saved['stages'][0]['image']
        );
    }
}
