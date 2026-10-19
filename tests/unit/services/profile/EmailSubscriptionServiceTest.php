<?php

namespace tests\unit\services\profile;

use app\models\User;
use app\models\UserProfile;
use app\services\profile\EmailSubscriptionService;
use Codeception\Test\Unit;
use Yii;

class EmailSubscriptionServiceTest extends Unit
{
    private const EMAIL = 'newsletter-guest@example.com';

    protected function _before(): void
    {
        parent::_before();
        $schema = Yii::$app->db->schema->getTableSchema('{{%users}}', true);
        if ($schema === null || !isset($schema->columns['subscription'])) {
            $this->markTestSkipped('users.subscription is not migrated in test DB.');
        }

        $profiles = UserProfile::find()->where(['email' => self::EMAIL])->all();
        foreach ($profiles as $profile) {
            User::deleteAll(['id' => (int)$profile->user_id]);
        }
        UserProfile::deleteAll(['email' => self::EMAIL]);
    }

    public function testCreatesUserAndSubscribesByEmail(): void
    {
        $service = new EmailSubscriptionService();
        $result = $service->applyForEmail(self::EMAIL, true);

        verify($result['subscription'])->true();

        $profile = UserProfile::find()->where(['email' => self::EMAIL])->one();
        verify($profile)->notNull();
        $user = User::findOne((int)$profile->user_id);
        verify($user)->notNull();
        verify((bool)$user->subscription)->true();
        verify($user->phone)->null();
    }

    public function testReusesExistingProfileEmail(): void
    {
        $user = new User(['type' => User::TYPE_CUSTOMER, 'phone' => '79990001122', 'username' => 'existing-subscriber']);
        $user->save(false);
        $profile = new UserProfile(['user_id' => (int)$user->id, 'email' => self::EMAIL]);
        $profile->save(false);

        $service = new EmailSubscriptionService();
        $result = $service->applyForEmail(self::EMAIL, true);

        verify($result['subscription'])->true();
        verify(UserProfile::find()->where(['email' => self::EMAIL])->count())->equals(1);
        $user->refresh();
        verify((bool)$user->subscription)->true();
    }
}
