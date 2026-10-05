<?php

namespace tests\unit\services;

use app\services\dealer\CashbackTierCalculator;

class CashbackTierCalculatorTest extends \Codeception\Test\Unit
{
    public function testTierPercents(): void
    {
        $calc = new CashbackTierCalculator();
        verify($calc->getPercentForTotal(500_000))->equals(0.0);
        verify($calc->getPercentForTotal(1_000_000))->equals(1.0);
        verify($calc->getPercentForTotal(2_000_000))->equals(1.5);
        verify($calc->getPercentForTotal(3_000_000))->equals(2.0);
    }

    public function testAccrualAmountProgressiveByTierBands(): void
    {
        $calc = new CashbackTierCalculator();
        verify($calc->calculateAccrualAmount(500_000))->equals(0.0);
        verify($calc->calculateAccrualAmount(1_000_000))->equals(0.0);
        verify($calc->calculateAccrualAmount(1_500_000))->equals(5000.0);
        verify($calc->calculateAccrualAmount(2_000_000))->equals(10000.0);
        verify($calc->calculateAccrualAmount(3_000_000))->equals(25000.0);
        verify($calc->calculateAccrualAmount(4_000_000))->equals(45000.0);
    }

    public function testAmountToNextThreshold(): void
    {
        $calc = new CashbackTierCalculator();
        verify($calc->amountToNextThreshold(240_588))->equals(759_412.0);
        verify($calc->amountToNextThreshold(1_000_000))->equals(1_000_000.0);
        verify($calc->amountToNextThreshold(3_500_000))->null();
    }
}
