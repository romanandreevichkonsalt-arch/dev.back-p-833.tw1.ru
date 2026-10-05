<?php

use yii\db\Migration;
use yii\db\Query;

class m260829_150000_catalog_url_slugs extends Migration
{
    /** @var array<string, string> internal slug => frontend url_slug */
    private array $categoryUrlSlugs = [
        'sofa' => 'divany',
        'armchair' => 'kresla',
        'combination' => 'kombinatsii',
    ];

    /** @var array<string, string> internal slug => frontend url_slug */
    private array $subcategoryUrlSlugs = [
        'straight' => 'pryamye',
        'corner' => 'uglovye',
        'compact' => 'kompaktnye',
        'modular' => 'modulnye',
        'armchair' => 'kreslo',
        'chair-bed' => 'kreslo-krovat',
    ];

    /** @var array<string, string> duplicate slug => canonical internal slug */
    private array $subcategoryMergeMap = [
        'pryamoy-divan' => 'straight',
        'uglovoy-divan' => 'corner',
        'modul' => 'modular',
        'kreslo' => 'armchair',
    ];

    public function safeUp(): void
    {
        $this->addColumn('{{%catalog_categories}}', 'url_slug', $this->string(64)->null()->after('slug'));
        $this->addColumn('{{%catalog_subcategories}}', 'url_slug', $this->string(64)->null()->after('slug'));

        foreach ($this->categoryUrlSlugs as $slug => $urlSlug) {
            $this->update('{{%catalog_categories}}', ['url_slug' => $urlSlug], ['slug' => $slug]);
        }

        foreach ($this->subcategoryUrlSlugs as $slug => $urlSlug) {
            $this->update('{{%catalog_subcategories}}', ['url_slug' => $urlSlug], ['slug' => $slug]);
        }

        $this->mergeDuplicateSubcategories();

        $this->createIndex('idx_catalog_categories_url_slug', '{{%catalog_categories}}', 'url_slug');
        $this->createIndex('idx_catalog_subcategories_url_slug', '{{%catalog_subcategories}}', 'url_slug');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_catalog_subcategories_url_slug', '{{%catalog_subcategories}}');
        $this->dropIndex('idx_catalog_categories_url_slug', '{{%catalog_categories}}');
        $this->dropColumn('{{%catalog_subcategories}}', 'url_slug');
        $this->dropColumn('{{%catalog_categories}}', 'url_slug');
    }

    private function mergeDuplicateSubcategories(): void
    {
        foreach ($this->subcategoryMergeMap as $duplicateSlug => $canonicalSlug) {
            $duplicate = (new Query())
                ->from('{{%catalog_subcategories}}')
                ->select(['id', 'category_id'])
                ->where(['slug' => $duplicateSlug])
                ->one();
            if ($duplicate === false) {
                continue;
            }

            $canonical = (new Query())
                ->from('{{%catalog_subcategories}}')
                ->select(['id'])
                ->where(['slug' => $canonicalSlug])
                ->one();
            if ($canonical === false) {
                continue;
            }

            $duplicateId = (int)$duplicate['id'];
            $canonicalId = (int)$canonical['id'];
            if ($duplicateId === $canonicalId) {
                continue;
            }

            $this->update('{{%catalog_products}}', ['subcategory_id' => $canonicalId], ['subcategory_id' => $duplicateId]);
            $this->update('{{%catalog_models}}', ['subcategory_id' => $canonicalId], ['subcategory_id' => $duplicateId]);
            $this->delete('{{%catalog_subcategories}}', ['id' => $duplicateId]);
        }
    }
}
