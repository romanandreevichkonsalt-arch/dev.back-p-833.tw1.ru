<?php

use yii\db\Migration;

class m260829_200000_fix_model_category_from_subcategory extends Migration
{
    public function safeUp(): void
    {
        $this->execute(<<<'SQL'
UPDATE {{%catalog_models}} model
INNER JOIN {{%catalog_subcategories}} subcategory ON subcategory.id = model.subcategory_id
SET model.category_id = subcategory.category_id
WHERE model.category_id <> subcategory.category_id
SQL);
    }

    public function safeDown(): bool
    {
        echo "m260829_200000_fix_model_category_from_subcategory cannot be reverted.\n";

        return false;
    }
}
