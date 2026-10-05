<?php

namespace tests\unit\services;

use app\services\payment\YooKassaHttpLogger;
use Codeception\Test\Unit;

class YooKassaHttpLoggerTest extends Unit
{
    public function testMaskSecretKey(): void
    {
        $this->assertSame(
            'test_****iqzQ',
            YooKassaHttpLogger::maskSecretKey('test_Ca_l68Qo6nA2vNtZqs9jkJ6cg_969zQMgX0R7_FiqzQ'),
        );
    }

    public function testConfigSummaryFlagsDevTestShop(): void
    {
        $summary = YooKassaHttpLogger::configSummary([
            'shopId' => '1470173',
            'secretKey' => 'test_abc',
            'returnUrl' => 'https://example/cart',
            'sendReceipt' => true,
        ]);

        $this->assertTrue($summary['shopIdMatchesExpectedDevTest']);
        $this->assertTrue($summary['isTestSecret']);
        $this->assertSame('1470173', $summary['shopId']);
    }
}
