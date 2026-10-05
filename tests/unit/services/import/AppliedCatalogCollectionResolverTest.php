<?php

namespace tests\unit\services\import;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\services\import\surface\AppliedCatalogCollectionResolver;
use Codeception\Test\Unit;

class AppliedCatalogCollectionResolverTest extends Unit
{
    public function testParsesAndResolvesExistingCollection(): void
    {
        $direction = new CatalogDirection([
            'slug' => 'test-dir-' . uniqid('', true),
            'label' => 'Test Direction',
            'is_active' => true,
        ]);
        verify($direction->save(false))->true();

        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => 'artemida-' . uniqid('', true),
            'name' => 'Артемида',
            'title' => 'Артемида',
            'label' => 'Артемида',
            'is_active' => true,
        ]);
        verify($collection->save())->true();

        $resolver = new AppliedCatalogCollectionResolver();
        $result = $resolver->resolveFromAppliedModelsText('Артемида, Unknown');

        verify($result['matched'])->arrayCount(1);
        verify($result['matched'][0])->equals((int)$collection->id);
        verify($result['unmatched'])->equals(['Unknown']);
    }
}
