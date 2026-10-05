<?php

namespace tests\unit\services;

use app\services\promotion\PromotionDealerApiService;
use Codeception\Test\Unit;

class PromotionDealerApiServiceTest extends Unit
{
    public function testListVisibleBannersReturnsArray(): void
    {
        $items = (new PromotionDealerApiService())->listVisibleBanners();

        $this->assertIsArray($items);
    }
}
