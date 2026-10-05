<?php

namespace tests\unit\models;

use app\exceptions\ApiValidationException;
use app\models\Lead;
use Codeception\Test\Unit;

class LeadTest extends Unit
{
    public function testContactsLeadValid(): void
    {
        $lead = new Lead([
            'type' => Lead::TYPE_CONTACTS,
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'phone' => '+79894232000',
            'comment' => 'Тест',
            'consent' => true,
        ]);

        verify($lead->validate())->true();
    }

    public function testRejectsInvalidPhone(): void
    {
        $lead = new Lead([
            'type' => Lead::TYPE_CONTACTS,
            'name' => 'Иван',
            'phone' => '8999',
            'consent' => true,
        ]);

        verify($lead->validate())->false();
        verify($lead->getErrors('phone'))->notEmpty();
    }

    public function testDesignersLeadOptionalFields(): void
    {
        $lead = new Lead([
            'type' => Lead::TYPE_DESIGNERS,
            'name' => 'Дизайнер',
            'phone' => '+79181234567',
            'consent' => true,
        ]);

        verify($lead->validate())->true();
    }
}
