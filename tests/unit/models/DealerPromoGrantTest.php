<?php

namespace tests\unit\models;

use app\models\DealerPromoGrant;
use app\models\PromoCodeTemplate;
use Codeception\Test\Unit;

class DealerPromoGrantTest extends Unit
{
    public function testCustomWithoutValidUntilIsUsableEvenWhenGrantExpiresAtPassed(): void
    {
        $grant = new DealerPromoGrant([
            'is_active' => true,
            'used_at' => null,
            'expires_at' => '2020-01-01 00:00:00',
        ]);
        $grant->populateRelation('template', new PromoCodeTemplate([
            'type' => PromoCodeTemplate::TYPE_CUSTOM,
            'valid_until' => null,
        ]));

        $this->assertTrue($grant->isUsable());
        $this->assertSame('Активен', $grant->getStatusLabel());
    }

    public function testCustomWithValidUntilInPastIsNotUsable(): void
    {
        $grant = new DealerPromoGrant([
            'is_active' => true,
            'used_at' => null,
            'expires_at' => null,
        ]);
        $grant->populateRelation('template', new PromoCodeTemplate([
            'type' => PromoCodeTemplate::TYPE_CUSTOM,
            'valid_until' => '2020-01-01',
        ]));

        $this->assertFalse($grant->isUsable());
        $this->assertSame('Истёк', $grant->getStatusLabel());
    }

    public function testUsedGrantIsNotUsable(): void
    {
        $grant = new DealerPromoGrant([
            'is_active' => false,
            'used_at' => '2026-01-01 12:00:00',
            'expires_at' => null,
        ]);
        $grant->populateRelation('template', new PromoCodeTemplate([
            'type' => PromoCodeTemplate::TYPE_CUSTOM,
            'valid_until' => null,
        ]));

        $this->assertFalse($grant->isUsable());
    }

    public function testNoveltyWithExpiredGrantIsNotUsable(): void
    {
        $grant = new DealerPromoGrant([
            'is_active' => true,
            'used_at' => null,
            'expires_at' => '2020-01-01 00:00:00',
        ]);
        $grant->populateRelation('template', new PromoCodeTemplate([
            'type' => PromoCodeTemplate::TYPE_NOVELTY,
            'valid_until' => null,
        ]));

        $this->assertFalse($grant->isUsable());
    }
}
