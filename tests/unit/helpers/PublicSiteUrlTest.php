<?php

namespace tests\unit\helpers;

use app\helpers\PublicSiteUrl;
use Codeception\Test\Unit;

class PublicSiteUrlTest extends Unit
{
    public function testDealerCabinetFromFrontendEnv(): void
    {
        verify(PublicSiteUrl::dealerCabinetUrl('https://mf-anna.ru', ''))
            ->equals('https://mf-anna.ru');
    }

    public function testDealerCabinetOverride(): void
    {
        verify(PublicSiteUrl::dealerCabinetUrl('https://ignored.example', 'https://custom.example/cabinet'))
            ->equals('https://custom.example/cabinet');
    }

    public function testDefaultFrontendWhenEnvEmpty(): void
    {
        verify(PublicSiteUrl::frontendUrl(''))->equals(PublicSiteUrl::DEFAULT_FRONTEND);
    }
}
