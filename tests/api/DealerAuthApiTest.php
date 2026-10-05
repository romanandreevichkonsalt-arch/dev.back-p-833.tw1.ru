<?php

namespace tests\api;

use app\models\ApiAccessToken;
use app\models\DealerProfile;
use app\models\User;
use app\services\dealer\DealerRegistrationService;
use Yii;
use yii\web\UnauthorizedHttpException;

class DealerAuthApiTest extends ApiTestCase
{
    private const TEST_INN = '7707083893';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->setQueryParams([]);

        $this->cleanupDealer(self::TEST_INN);
    }

    protected function _after(): void
    {
        $this->cleanupDealer(self::TEST_INN);
        parent::_after();
    }

    public function testDealerLoginAndProfileWithBearer(): void
    {
        $password = $this->createDealerViaRegistration();

        $tokenResponse = $this->postJson('api/v1/dealer/auth/login', [
            'username' => 'd' . self::TEST_INN,
            'password' => $password,
        ]);

        verify($tokenResponse)->arrayHasKey('access_token');
        verify($tokenResponse)->arrayHasKey('token_type');
        verify($tokenResponse)->arrayHasKey('expires_in');
        verify($tokenResponse)->arrayHasKey('profileComplete');
        verify($tokenResponse)->arrayHasKey('guestSync');
        verify($tokenResponse['token_type'])->equals('Bearer');

        $this->withBearer($tokenResponse['access_token']);
        $profile = $this->getJson('api/v1/dealer/profile');

        verify($profile)->arrayHasKey('id');
        verify($profile)->arrayHasKey('username');
        verify($profile['username'])->equals('d' . self::TEST_INN);
        verify($profile['inn'])->equals(self::TEST_INN);
        verify($profile['profileComplete'])->true();
    }

    public function testDealerLoginAcceptsLoginFieldAlias(): void
    {
        $password = $this->createDealerViaRegistration();

        $tokenResponse = $this->postJson('api/v1/dealer/auth/login', [
            'login' => 'd' . self::TEST_INN,
            'password' => $password,
        ]);

        verify($tokenResponse)->arrayHasKey('access_token');
    }

    public function testDealerBonusesRequiresBearer(): void
    {
        $password = $this->createDealerViaRegistration();

        $tokenResponse = $this->postJson('api/v1/dealer/auth/login', [
            'username' => 'd' . self::TEST_INN,
            'password' => $password,
        ]);

        $this->withBearer($tokenResponse['access_token']);
        $bonuses = $this->getJson('api/v1/dealer/bonuses');

        verify($bonuses)->arrayHasKey('promos');
        verify($bonuses)->arrayHasKey('cashback');
    }

    public function testDealerProfileRejectsMissingBearer(): void
    {
        $this->createDealerViaRegistration();

        $this->expectException(UnauthorizedHttpException::class);
        $this->getJson('api/v1/dealer/profile');
    }

    public function testDealerLoginRejectsInvalidCredentials(): void
    {
        $this->createDealerViaRegistration();

        $this->expectException(UnauthorizedHttpException::class);
        $this->postJson('api/v1/dealer/auth/login', [
            'username' => 'd' . self::TEST_INN,
            'password' => 'wrong-password',
        ]);
    }

    private function createDealerViaRegistration(): string
    {
        $result = (new DealerRegistrationService())->create(
            'ООО Тест Дилер',
            self::TEST_INN,
            'Иван Менеджер',
            'dealer-test@example.com',
            '79998886644',
            false,
        );

        return $result['password'];
    }

    private function cleanupDealer(string $inn): void
    {
        $profile = DealerProfile::find()->where(['inn' => $inn])->one();
        if ($profile === null) {
            return;
        }

        $userId = (int)$profile->user_id;
        ApiAccessToken::deleteAll(['user_id' => $userId]);
        $profile->delete();

        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
