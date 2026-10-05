<?php

namespace tests\unit\services;

use app\services\dealer\DealerPromoService;
use Codeception\Test\Unit;

class DealerPromoServiceTest extends Unit
{
    public function testBuildNoveltyPromoCodeUsesCollectionSlug(): void
    {
        $this->assertSame('NOVINKA_ARTEMIDA', DealerPromoService::buildNoveltyPromoCode('artemida'));
        $this->assertSame('NOVINKA_ADRIANO', DealerPromoService::buildNoveltyPromoCode('adriano'));
        $this->assertSame('NOVINKA_LINEA_1', DealerPromoService::buildNoveltyPromoCode('linea-1'));
        $this->assertSame('', DealerPromoService::buildNoveltyPromoCode(''));
    }
}
