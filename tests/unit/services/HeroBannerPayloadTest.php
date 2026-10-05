<?php

namespace tests\unit\services;

use app\services\content\HeroBannerPayload;
use Codeception\Test\Unit;

class HeroBannerPayloadTest extends Unit
{
    public function testNormalizeLegacyTitleSubtitle(): void
    {
        $normalized = HeroBannerPayload::normalize([
            'title' => 'FAQ',
            'subtitle' => 'Ответы на вопросы',
            'image' => ['src' => '/uploads/media/faq.webp', 'alt' => 'FAQ'],
        ]);

        $this->assertSame('FAQ', $normalized['collectionTitle']);
        $this->assertSame('Ответы на вопросы', $normalized['tagline']);
        $this->assertSame('FAQ', $normalized['title']);
        $this->assertSame('Ответы на вопросы', $normalized['subtitle']);
        $this->assertSame('FAQ', $normalized['brandTitle']);
        $this->assertSame('/uploads/media/faq.webp', $normalized['imageDesktop']['src']);
    }

    public function testNormalizeLabelAndBrandTitleAliases(): void
    {
        $normalized = HeroBannerPayload::normalize([
            'label' => 'О нас',
            'brandTitle' => 'МФ Анна',
            'tagline' => 'Мастерство',
        ]);

        $this->assertSame('О нас', $normalized['label']);
        $this->assertSame('МФ Анна', $normalized['brandTitle']);
        $this->assertSame('МФ Анна', $normalized['title']);
        $this->assertSame('Мастерство', $normalized['subtitle']);
    }

    public function testFromPostAndToFormRoundTrip(): void
    {
        $payload = HeroBannerPayload::fromPost([
            'collection_label' => 'Коллекция',
            'collection_title' => 'А+',
            'tagline' => 'Текст справа',
            'year' => '2026',
            'image_desktop_src' => '/uploads/media/a.webp',
            'image_desktop_alt' => 'A+',
            'image_mobile_src' => '/uploads/media/a-m.webp',
            'image_mobile_alt' => 'A+ mobile',
        ]);

        $form = HeroBannerPayload::toForm($payload);

        $this->assertSame('Коллекция', $form['collection_label']);
        $this->assertSame('А+', $form['collection_title']);
        $this->assertSame('Текст справа', $form['tagline']);
        $this->assertSame('2026', $form['year']);
        $this->assertSame('/uploads/media/a.webp', $form['image_desktop_src']);
    }
}
