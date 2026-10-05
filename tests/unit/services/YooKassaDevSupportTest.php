<?php

namespace tests\unit\services;

use app\services\payment\YooKassaDevSupport;
use Codeception\Test\Unit;

class YooKassaDevSupportTest extends Unit
{
    public function testEnabledForTestSecretPrefix(): void
    {
        $this->assertTrue(YooKassaDevSupport::testEndpointsEnabled([
            'secretKey' => 'test_abc',
        ]));
        $this->assertFalse(YooKassaDevSupport::testEndpointsEnabled([
            'secretKey' => 'live_abc',
        ]));
    }

    public function testScenariosPayloadHasCards(): void
    {
        $payload = YooKassaDevSupport::scenariosPayload();
        $this->assertArrayHasKey('testCards', $payload);
        $this->assertNotEmpty($payload['testCards']['success']);
        $this->assertNotEmpty($payload['testCards']['insufficientFunds']);
    }
}
