<?php

use app\helpers\SlugHelper;
use yii\db\Migration;
use yii\db\Query;

/**
 * Синхронизация справочника catalog_colors с финальным списком базовых цветов ткани.
 * Обновляет label/hex/sort_order, добавляет недостающие, удаляет лишние без ссылок.
 */
class m260815_161000_catalog_fabric_base_colors_sync extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');
        $keepSlugs = [];

        foreach ($this->colors() as $sortOrder => $row) {
            $label = $row['label'];
            $slug = $row['slug'] ?? SlugHelper::slugify($label);
            if ($slug === '') {
                continue;
            }

            $keepSlugs[] = $slug;
            $attributes = [
                'label' => $label,
                'hex_color' => $row['hex'] ?? null,
                'sort_order' => $sortOrder * 10,
                'is_active' => true,
                'updated_at' => $now,
            ];

            $existing = (new Query())
                ->from('{{%catalog_colors}}')
                ->where(['slug' => $slug])
                ->one($this->db);

            if ($existing !== false) {
                $this->update('{{%catalog_colors}}', $attributes, ['id' => (int)$existing['id']]);
                continue;
            }

            $this->insert('{{%catalog_colors}}', array_merge($attributes, [
                'slug' => $slug,
                'swatch_media_id' => null,
                'created_at' => $now,
            ]));
        }

        $keepSlugs = array_values(array_unique($keepSlugs));
        if ($keepSlugs === []) {
            return;
        }

        $extraIds = (new Query())
            ->select('id')
            ->from('{{%catalog_colors}}')
            ->where(['not in', 'slug', $keepSlugs])
            ->column($this->db);

        foreach ($extraIds as $colorId) {
            $colorId = (int)$colorId;
            if ($this->colorIsReferenced($colorId)) {
                continue;
            }

            $this->delete('{{%catalog_color_images}}', ['color_id' => $colorId]);
            $this->delete('{{%catalog_colors}}', ['id' => $colorId]);
        }
    }

    public function safeDown(): void
    {
        throw new \yii\base\NotSupportedException('Откат синхронизации базовых цветов ткани не поддерживается.');
    }

    /**
     * @return list<array{label:string,slug?:string,hex?:string}>
     */
    private function colors(): array
    {
        return [
            ['label' => 'Белый', 'slug' => 'belyy', 'hex' => '#FFFFFF'],
            ['label' => 'Кремовый', 'hex' => '#F5F0E6'],
            ['label' => 'Бежевый', 'slug' => 'bezhevyy', 'hex' => '#D4C4A8'],
            ['label' => 'Песочный', 'hex' => '#D9C4A0'],
            ['label' => 'Капучино', 'hex' => '#C4A484'],

            ['label' => 'Серый', 'slug' => 'seryy', 'hex' => '#9E9E9E'],
            ['label' => 'Чёрный', 'hex' => '#1F1F1F'],

            ['label' => 'Коричневый', 'slug' => 'korichnevyy', 'hex' => '#7A4E2D'],
            ['label' => 'Терракота', 'slug' => 'terrakota', 'hex' => '#C86B4A'],
            ['label' => 'Оранжевый', 'hex' => '#E07A2D'],

            ['label' => 'Жёлтый', 'hex' => '#E6C84A'],
            ['label' => 'Горчичный', 'slug' => 'gorchichnyy', 'hex' => '#C9A227'],

            ['label' => 'Красный', 'hex' => '#C62828'],
            ['label' => 'Бордовый', 'hex' => '#7B1E3A'],
            ['label' => 'Розовый', 'hex' => '#E8A0A8'],

            ['label' => 'Синий', 'hex' => '#2F5F9E'],
            ['label' => 'Голубой', 'hex' => '#7EB6D8'],

            ['label' => 'Зелёный', 'hex' => '#4F8A4A'],
            ['label' => 'Оливковый', 'slug' => 'olivkovyy', 'hex' => '#6B6F3A'],
            ['label' => 'Мятный', 'hex' => '#9FD4C4'],

            ['label' => 'Фиолетовый', 'hex' => '#6A4C93'],
            ['label' => 'Сиреневый', 'hex' => '#B39DDB'],
        ];
    }

    private function colorIsReferenced(int $colorId): bool
    {
        if ((new Query())->from('{{%catalog_fabric_collection_colors}}')->where(['color_id' => $colorId])->exists($this->db)) {
            return true;
        }

        return (new Query())->from('{{%catalog_color_images}}')->where(['color_id' => $colorId])->exists($this->db);
    }
}
