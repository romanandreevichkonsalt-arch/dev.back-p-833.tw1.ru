<?php

namespace tests\api;

use app\models\Lead;
use Yii;

class LeadsApiTest extends ApiTestCase
{
    public function testCreateContactsLead(): void
    {
        $before = Lead::find()->count();

        Yii::$app->response->clear();
        $response = $this->postJson('api/v1/leads/create', [
            'type' => 'contacts',
            'name' => 'Тест Тестов',
            'email' => 'test@example.com',
            'phone' => '+79894232000',
            'comment' => 'Тестовая заявка',
            'consent' => true,
        ]);

        verify(Yii::$app->response->statusCode)->equals(201);
        verify($response)->arrayHasKey('ok');
        verify($response)->arrayHasKey('id');
        verify($response['ok'])->true();
        verify(Lead::find()->count())->equals($before + 1);
    }

    public function testCreateDesignersLead(): void
    {
        $response = $this->postJson('api/v1/leads/create', [
            'type' => 'designers',
            'name' => 'Дизайнер Тест',
            'email' => 'designer@example.com',
            'phone' => '+79181234567',
            'studio' => 'Test Studio',
            'city' => 'Ростов-на-Дону',
            'consent' => true,
        ]);

        verify($response['ok'])->true();
        $lead = Lead::findOne((int)$response['id']);
        verify($lead)->notNull();
        verify($lead->type)->equals('designers');
        verify($lead->studio)->equals('Test Studio');
    }

    public function testRejectsLeadWithoutConsent(): void
    {
        $this->expectException(\app\exceptions\ApiValidationException::class);
        $this->postJson('api/v1/leads/create', [
            'type' => 'contacts',
            'name' => 'Тест',
            'phone' => '+79894232000',
            'consent' => false,
        ]);
    }
}
