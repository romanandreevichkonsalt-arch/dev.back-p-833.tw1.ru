<?php

namespace tests\unit\models;

use app\modules\admin\models\PromoCodeForm;
use Codeception\Test\Unit;

class PromoCodeFormTest extends Unit
{
    public function testEmptyValidUntilPassesValidation(): void
    {
        $form = new PromoCodeForm();
        $form->load([
            'code' => 'promo2026',
            'title' => 'Тестовый промокод',
            'discount_percent' => '10',
            'valid_until' => '',
        ], '');

        $this->assertTrue($form->validate(), json_encode($form->errors, JSON_UNESCAPED_UNICODE));
        $this->assertNull($form->valid_until);
        $this->assertSame('PROMO2026', $form->code);
    }

    public function testLowercaseLatinCodePassesValidation(): void
    {
        $form = new PromoCodeForm();
        $form->load([
            'code' => 'promo_test_1',
            'title' => 'Тестовый промокод',
            'discount_percent' => '10',
            'valid_until' => '',
        ], '');

        $this->assertTrue($form->validate(), json_encode($form->errors, JSON_UNESCAPED_UNICODE));
        $this->assertSame('PROMO_TEST_1', $form->code);
    }

    public function testValidUntilDatePassesValidation(): void
    {
        $form = new PromoCodeForm();
        $form->load([
            'code' => 'PROMO30',
            'title' => 'Тестовый промокод',
            'discount_percent' => '10',
            'valid_until' => '2026-12-31',
        ], '');

        $this->assertTrue($form->validate(), json_encode($form->errors, JSON_UNESCAPED_UNICODE));
        $this->assertSame('2026-12-31', $form->valid_until);
    }
}
