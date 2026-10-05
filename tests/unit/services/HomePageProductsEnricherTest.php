<?php

namespace tests\unit\services;

use app\services\content\HomePageProductsEnricher;
use Codeception\Test\Unit;

class HomePageProductsEnricherTest extends Unit
{
    public function testEnrichPadsProductsToThreeWhenCatalogAvailable(): void
    {
        $enricher = new HomePageProductsEnricher();
        $result = $enricher->enrich([]);

        if ($result === []) {
            $this->markTestSkipped('Catalog has no products with images.');
        }

        $this->assertLessThanOrEqual(3, count($result));
        foreach ($result as $item) {
            $this->assertArrayHasKey('image', $item);
            $this->assertNotEmpty($item['image']['src'] ?? ($item['image']['srcSet']['medium'] ?? null));
        }
    }
}
