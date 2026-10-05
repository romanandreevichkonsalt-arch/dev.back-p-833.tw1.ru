<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_190000_designers_gallery_stack extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'designers'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $galleryRow = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'gallery'])
            ->one();

        $samplesRow = (new Query())
            ->select(['data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'samples'])
            ->one();

        $photoStackRow = (new Query())
            ->select(['data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'photoStack'])
            ->one();

        if ($galleryRow === false) {
            return;
        }

        $galleryData = json_decode((string)$galleryRow['data'], true);
        if (!is_array($galleryData)) {
            $galleryData = [];
        }

        $samplesData = $samplesRow !== false
            ? json_decode((string)$samplesRow['data'], true)
            : null;
        $photoStackData = $photoStackRow !== false
            ? json_decode((string)$photoStackRow['data'], true)
            : null;

        $galleryData = $this->buildGalleryStack($galleryData, $samplesData, $photoStackData);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($galleryData, JSON_UNESCAPED_UNICODE),
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_GALLERY_STACK_SECTION,
        ], ['id' => $galleryRow['id']]);

        $this->delete('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => ['samples', 'photoStack'],
        ]);
    }

    public function safeDown(): void
    {
        // Структура gallery на designers не восстанавливается автоматически.
    }

    /**
     * @param array<string, mixed> $gallery
     * @param array<string, mixed>|null $samples
     * @param array<int, array<string, mixed>>|null $photoStack
     * @return array<string, mixed>
     */
    private function buildGalleryStack(array $gallery, ?array $samples, ?array $photoStack): array
    {
        $defaults = $this->defaultGalleryStack();
        $photos = [];

        if (isset($gallery['photos']) && is_array($gallery['photos'])) {
            $photos = $gallery['photos'];
        } elseif (isset($gallery['slides']) && is_array($gallery['slides'])) {
            foreach ($gallery['slides'] as $slide) {
                if (!is_array($slide) || !isset($slide['image'])) {
                    continue;
                }
                $photos[] = ['image' => $slide['image']];
            }
        }

        if ($photos === [] && is_array($photoStack)) {
            $photos = $photoStack;
        }

        if (isset($samples['image']) && is_array($samples['image'])) {
            $photos[] = ['image' => $samples['image']];
        }

        if ($photos === []) {
            $photos = $defaults['photos'];
        }

        $text = trim((string)($gallery['text'] ?? ''));
        if ($text === '' && is_array($samples)) {
            $text = trim((string)($samples['text'] ?? ''));
        }
        if ($text === '') {
            $text = $defaults['text'];
        }

        return [
            'text' => $text,
            'photos' => $photos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultGalleryStack(): array
    {
        return [
            'text' => 'Для нас важно, чтобы вы и ваши клиенты могли почувствовать качество материалов. '
                . 'Мы предоставляем сэмпл-боксы с выкрасами дерева и фрагментами текстиля для работы на объекте '
                . 'или согласования в вашей студии',
            'photos' => [
                [
                    'image' => [
                        'src' => 'https://dev.back-p-833.tw1.ru/images/designers/stack-1.webp',
                        'alt' => 'Интерьер',
                    ],
                    'rotate' => 5,
                    'offsetX' => -4,
                    'offsetY' => 6,
                ],
                [
                    'image' => [
                        'src' => 'https://dev.back-p-833.tw1.ru/images/designers/samples.webp',
                        'alt' => 'Образцы',
                    ],
                    'rotate' => -3,
                    'offsetX' => 6,
                    'offsetY' => -4,
                ],
                [
                    'image' => [
                        'src' => 'https://dev.back-p-833.tw1.ru/images/designers/gallery-1.webp',
                        'alt' => 'Салон',
                    ],
                    'rotate' => 2,
                    'offsetX' => -2,
                    'offsetY' => 3,
                ],
            ],
        ];
    }
}
