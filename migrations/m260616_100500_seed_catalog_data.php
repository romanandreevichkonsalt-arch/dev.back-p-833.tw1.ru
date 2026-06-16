<?php

use yii\db\Migration;

class m260616_100500_seed_catalog_data extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->insert('{{%categories}}', [
            'id' => 1,
            'parent_id' => null,
            'name' => 'Диваны и кресла',
            'slug' => 'divany-i-kresla',
            'description' => 'Мягкая мебель для гостиной и спальни',
            'seo_title' => 'Диваны и кресла — купить от производителя',
            'seo_description' => 'Каталог диванов и кресел с доставкой',
            'seo_h1' => 'Диваны и кресла',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%categories}}', [
            'id' => 2,
            'parent_id' => 1,
            'name' => 'Прямые диваны',
            'slug' => 'pryamye-divany',
            'description' => 'Прямые диваны для гостиной',
            'seo_title' => 'Прямые диваны',
            'seo_description' => 'Купить прямой диван',
            'seo_h1' => 'Прямые диваны',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%categories}}', [
            'id' => 3,
            'parent_id' => null,
            'name' => 'Кровати и матрасы',
            'slug' => 'krovati-i-matrasy',
            'description' => 'Кровати и матрасы для спальни',
            'seo_title' => 'Кровати и матрасы',
            'seo_description' => 'Каталог кроватей и матрасов',
            'seo_h1' => 'Кровати и матрасы',
            'is_active' => true,
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%attribute_definitions}}', [
            'code' => 'color',
            'name' => 'Цвет',
            'type' => 'string',
            'is_filterable' => true,
            'is_required' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%attribute_definitions}}', [
            'code' => 'material',
            'name' => 'Материал',
            'type' => 'string',
            'is_filterable' => true,
            'is_required' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%attribute_definitions}}', [
            'code' => 'width',
            'name' => 'Ширина, см',
            'type' => 'number',
            'is_filterable' => true,
            'is_required' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%attribute_definitions}}', [
            'code' => 'is_new',
            'name' => 'Новинка',
            'type' => 'bool',
            'is_filterable' => true,
            'is_required' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%products}}', [
            'id' => 1,
            'name' => 'Диван прямой Брера Букле Синий',
            'slug' => 'divan-pryamoy-brera-bukle-siniy',
            'sku' => 'SOFA-BRERA-BLUE',
            'description' => 'Современный прямой диван с мягкой обивкой',
            'price' => 114990.00,
            'old_price' => null,
            'is_active' => true,
            'stock_qty' => 5,
            'seo_title' => 'Диван прямой Брера Букле Синий — купить',
            'seo_description' => 'Диван прямой Брера в синем букле',
            'seo_h1' => 'Диван прямой Брера Букле Синий',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert('{{%product_categories}}', [
            'product_id' => 1,
            'category_id' => 1,
        ]);
        $this->insert('{{%product_categories}}', [
            'product_id' => 1,
            'category_id' => 2,
        ]);

        $this->insert('{{%product_attributes}}', [
            'product_id' => 1,
            'attribute_code' => 'color',
            'value_string' => 'синий',
            'value_number' => null,
            'value_bool' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('{{%product_attributes}}', [
            'product_id' => 1,
            'attribute_code' => 'material',
            'value_string' => 'букле',
            'value_number' => null,
            'value_bool' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('{{%product_attributes}}', [
            'product_id' => 1,
            'attribute_code' => 'width',
            'value_string' => null,
            'value_number' => 220,
            'value_bool' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert('{{%product_attributes}}', [
            'product_id' => 1,
            'attribute_code' => 'is_new',
            'value_string' => null,
            'value_number' => null,
            'value_bool' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%product_attributes}}', ['product_id' => 1]);
        $this->delete('{{%product_categories}}', ['product_id' => 1]);
        $this->delete('{{%products}}', ['id' => 1]);
        $this->delete('{{%attribute_definitions}}', ['code' => ['color', 'material', 'width', 'is_new']]);
        $this->delete('{{%categories}}', ['id' => [1, 2, 3]]);
    }
}
