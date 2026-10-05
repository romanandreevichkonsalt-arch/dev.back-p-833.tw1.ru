<?php

use app\helpers\SlugHelper;
use yii\db\Migration;
use yii\db\Query;

/**
 * Базовые цвета мебельной ткани для справочника catalog_colors.
 * Идемпотентно: не дублирует существующие slug и названия.
 */
class m260815_160000_catalog_fabric_base_colors extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->colors() as $sortOrder => $row) {
            $label = $row['label'];
            $slug = $row['slug'] ?? SlugHelper::slugify($label);
            if ($slug === '' || $this->colorExists($slug, $label)) {
                continue;
            }

            $this->insert('{{%catalog_colors}}', [
                'slug' => $slug,
                'label' => $label,
                'hex_color' => $row['hex'] ?? null,
                'swatch_media_id' => null,
                'sort_order' => $sortOrder * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $slugs = [];
        foreach ($this->colors() as $row) {
            $slug = $row['slug'] ?? SlugHelper::slugify($row['label']);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        if ($slugs !== []) {
            $this->delete('{{%catalog_colors}}', ['slug' => array_values(array_unique($slugs))]);
        }
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

    private function colorExists(string $slug, string $label): bool
    {
        if ((new Query())->from('{{%catalog_colors}}')->where(['slug' => $slug])->exists($this->db)) {
            return true;
        }

        $normalizedLabel = mb_strtolower(trim($label));

        return (new Query())
            ->from('{{%catalog_colors}}')
            ->where('LOWER([[label]]) = :label', [':label' => $normalizedLabel])
            ->exists($this->db);
    }
}
