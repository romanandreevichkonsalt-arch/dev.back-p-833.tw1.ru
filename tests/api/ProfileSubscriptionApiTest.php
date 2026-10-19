<?php

namespace tests\api;

use app\models\User;
use app\models\UserProfile;

class ProfileSubscriptionApiTest extends ApiTestCase
{
    private const EMAIL = 'footer-subscribe@example.com';

    protected function _before(): void
    {
        parent::_before();
        $schema = \Yii::$app->db->schema->getTableSchema('{{%users}}', true);
        if ($schema === null || !isset($schema->columns['subscription'])) {
            $this->markTestSkipped('users.subscription is not migrated in test DB.');
        }

        foreach (UserProfile::find()->where(['email' => self::EMAIL])->all() as $profile) {
            User::deleteAll(['id' => (int)$profile->user_id]);
        }
        UserProfile::deleteAll(['email' => self::EMAIL]);
        \Yii::$app->request->headers->remove('Authorization');
    }

    public function testGuestCanSubscribeWithEmail(): void
    {
        $response = $this->runAction('api/v1/profile/subscription', [], [
            'email' => self::EMAIL,
            'subscription' => true,
        ], 'PUT');

        verify($response)->arrayHasKey('subscription');
        verify($response['subscription'])->true();

        $profile = UserProfile::find()->where(['email' => self::EMAIL])->one();
        verify($profile)->notNull();
        $user = User::findOne((int)$profile->user_id);
        verify($user)->notNull();
        verify((bool)$user->subscription)->true();
    }

    public function testGuestSubscribeRequiresEmail(): void
    {
        $this->expectException(\app\exceptions\ApiValidationException::class);
        $this->runAction('api/v1/profile/subscription', [], [
            'subscription' => true,
        ], 'PUT');
    }
}
