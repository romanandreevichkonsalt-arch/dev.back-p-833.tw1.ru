<?php

namespace tests\unit\services;

use app\services\catalog\CatalogUrlSlugResolver;
use Codeception\Test\Unit;

class CatalogUrlSlugResolverTest extends Unit
{
    public function testNormalizeSortAlias(): void
    {
        $resolver = new CatalogUrlSlugResolver();
        $normalized = $resolver->normalizeRequestParams([
            'direction' => 'a-plus',
            'category' => 'divany',
            'sort' => 'price-asc',
        ]);

        verify($normalized['direction'])->equals('a-plus');
        verify($normalized['category'])->equals('divany');
        verify($normalized['sort'])->equals('price_asc');
    }

    public function testBuildProductUrl(): void
    {
        $resolver = new CatalogUrlSlugResolver();
        verify($resolver->buildProductUrl('turin-grey'))->equals('/product/turin-grey');
    }
}
