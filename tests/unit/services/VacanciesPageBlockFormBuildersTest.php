<?php

namespace tests\unit\services;

use app\services\content\BlockFormBuilders;
use Codeception\Test\Unit;

class VacanciesPageBlockFormBuildersTest extends Unit
{
    public function testVacanciesValuesRoundTrip(): void
    {
        $data = [
            'title' => 'Наши ценности',
            'paragraphs' => ['Первый абзац.', 'Второй абзац.'],
            'image' => ['src' => '/uploads/vacancies/values.webp', 'alt' => 'Команда'],
            'slides' => [
                ['image' => ['src' => '/uploads/vacancies/g1.webp', 'alt' => 'Галерея 1']],
            ],
        ];

        $form = BlockFormBuilders::vacanciesValuesToForm($data);
        $saved = BlockFormBuilders::vacanciesValuesFromPost($form);

        $this->assertSame($data['title'], $saved['title']);
        $this->assertSame($data['paragraphs'], $saved['paragraphs']);
        $this->assertSame($data['image'], $saved['image']);
    }

    public function testVacanciesGalleryRoundTrip(): void
    {
        $data = [
            'slides' => [
                ['image' => ['src' => '/uploads/vacancies/1.webp', 'alt' => 'Фото 1']],
                ['image' => ['src' => '/uploads/vacancies/2.webp', 'alt' => 'Фото 2']],
            ],
        ];

        $form = BlockFormBuilders::vacanciesGalleryToForm($data);
        $saved = BlockFormBuilders::vacanciesGalleryFromPost($form);

        $this->assertSame($data, $saved);
    }
}
