<?php

namespace tests\unit\services;

use app\services\payment\YooKassaWebhookIpAllowlist;
use Codeception\Test\Unit;

class YooKassaWebhookIpAllowlistTest extends Unit
{
    public function testAllowsDocumentedRanges(): void
    {
        $cidrs = [
            '185.71.76.0/27',
            '185.71.77.0/27',
            '77.75.153.0/25',
            '77.75.154.128/25',
            '77.75.156.11',
            '77.75.156.35',
            '2a02:5180::/32',
        ];

        $this->assertTrue(YooKassaWebhookIpAllowlist::isAllowed('185.71.76.1', $cidrs));
        $this->assertTrue(YooKassaWebhookIpAllowlist::isAllowed('77.75.156.11', $cidrs));
        $this->assertTrue(YooKassaWebhookIpAllowlist::isAllowed('77.75.153.10', $cidrs));
        $this->assertFalse(YooKassaWebhookIpAllowlist::isAllowed('8.8.8.8', $cidrs));
        $this->assertFalse(YooKassaWebhookIpAllowlist::isAllowed('127.0.0.1', $cidrs));
    }
}
