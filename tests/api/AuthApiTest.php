<?php

namespace tests\api;

use app\models\SmsCode;
use app\models\User;

class AuthApiTest extends ApiTestCase
{
  private const TEST_PHONE = '79894232000';

    protected function _before(): void
    {
        parent::_before();
        $schema = \Yii::$app->db->schema->getTableSchema('{{%users}}', true);
        if ($schema === null || !isset($schema->columns['subscription'])) {
            $this->markTestSkipped('users.subscription is not migrated in test DB.');
        }
        SmsCode::deleteAll(['phone' => self::TEST_PHONE]);
        User::deleteAll(['phone' => self::TEST_PHONE]);
    }

    public function testRequestCodeReturnsContractShape(): void
    {
        $response = $this->postJson('api/v1/auth/request-code', [
            'phone' => self::TEST_PHONE,
        ]);

        verify($response)->arrayHasKey('ok');
        verify($response)->arrayHasKey('expires_in');
        verify($response['ok'])->true();
        verify(isset($response['code']))->false();
        verify(isset($response['phone']))->false();
    }

    public function testAuthFlowAndProfile(): void
    {
        $this->postJson('api/v1/auth/request-code', ['phone' => self::TEST_PHONE]);

        $smsCode = SmsCode::find()
            ->where(['phone' => self::TEST_PHONE, 'used_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        verify($smsCode)->notNull();

        $tokenResponse = $this->postJson('api/v1/auth/verify-code', [
            'phone' => self::TEST_PHONE,
            'code' => $smsCode->code,
        ]);

        verify($tokenResponse)->arrayHasKey('access_token');
        verify($tokenResponse)->arrayHasKey('token_type');
        verify($tokenResponse)->arrayHasKey('expires_in');
        verify($tokenResponse)->arrayHasKey('guestSync');
        verify($tokenResponse['token_type'])->equals('Bearer');
        verify($tokenResponse['guestSync']['skipped'])->true();
        verify(isset($tokenResponse['user']))->false();

        $this->withBearer($tokenResponse['access_token']);
        $profile = $this->getJson('api/v1/profile/me');

        verify($profile)->arrayHasKey('id');
        verify($profile)->arrayHasKey('name');
        verify($profile)->arrayHasKey('phone');
        verify($profile)->arrayHasKey('email');
        verify($profile)->arrayHasKey('avatar');
        verify($profile)->arrayHasKey('subscription');
        verify($profile['subscription'])->false();
        verify($profile['phone'])->equals(self::TEST_PHONE);

        $subscribed = $this->runAction('api/v1/profile/subscription', [], ['subscription' => true], 'PUT');
        verify($subscribed)->arrayHasKey('subscription');
        verify($subscribed['subscription'])->true();

        $profileAfter = $this->getJson('api/v1/profile/me');
        verify($profileAfter['subscription'])->true();

        $unsubscribed = $this->runAction('api/v1/profile/subscription', [], ['subscription' => false], 'PUT');
        verify($unsubscribed['subscription'])->false();
    }

    public function testRequestCodeRejectsInvalidPhone(): void
    {
        $this->expectException(\yii\web\BadRequestHttpException::class);
        $this->postJson('api/v1/auth/request-code', ['phone' => '123']);
    }
}
