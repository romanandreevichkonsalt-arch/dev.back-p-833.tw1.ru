<?php

namespace tests\unit\services\import;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\services\import\catalog\CatalogImportReferenceResolver;
use Codeception\Test\Unit;

class CatalogImportReferenceResolverTest extends Unit
{
    public function testResolveCollectionUsesDirectionSuffixWhenSlugTaken(): void
    {
        $aPlus = CatalogDirection::find()->where(['slug' => 'a-plus'])->one();
        $line1 = CatalogDirection::find()->where(['slug' => 'line-1'])->one();
        verify($aPlus)->notNull();
        verify($line1)->notNull();

        $existing = CatalogCollection::find()
            ->where(['direction_id' => (int)$aPlus->id, 'slug' => 'artemida'])
            ->one();
        if ($existing === null) {
            $existing = new CatalogCollection([
                'direction_id' => (int)$aPlus->id,
                'name' => 'Коллекция Артемида',
                'slug' => 'artemida',
                'label' => 'Артемида',
                'title' => 'Артемида',
                'href' => '/catalog/artemida',
                'is_active' => true,
            ]);
            verify($existing->save(false))->true();
        }

        $resolver = new CatalogImportReferenceResolver();
        $lineCollection = $resolver->resolveCollection((int)$line1->id, 'Артемида');

        verify($lineCollection->direction_id)->equals((int)$line1->id);
        verify($lineCollection->slug)->equals('artemida-line-1');
    }

    public function testResolveFabricCollectionByNameIsCaseInsensitive(): void
    {
        $fabric = CatalogFabricCollection::find()->where(['slug' => 'gucci-test-import'])->one();
        if ($fabric === null) {
            $fabric = new CatalogFabricCollection([
                'name' => 'GUCCI',
                'slug' => 'gucci-test-import',
                'is_active' => true,
            ]);
            verify($fabric->save(false))->true();
        }

        $resolver = new CatalogImportReferenceResolver();
        verify($resolver->resolveFabricCollectionByName('gucci')?->id)->equals((int)$fabric->id);
        verify($resolver->resolveFabricCollectionByName(' GUCCI ')?->id)->equals((int)$fabric->id);
    }
}
