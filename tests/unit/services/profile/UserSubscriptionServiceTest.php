<?php

namespace tests\unit\services\profile;

use app\exceptions\ApiValidationException;
use app\models\User;
use app\services\profile\UserSubscriptionService;
use Codeception\Test\Unit;
use Yii;

class UserSubscriptionServiceTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        $schema = Yii::$app->db->schema->getTableSchema('{{%users}}', true);
        if ($schema !== null && !isset($schema->columns['subscription'])) {
            $this->markTestSkipped('users.subscription is not migrated in test DB.');
        }
    }

    public function testUpdatesSubscriptionFlag(): void
    {
        $user = new User();
        $user->phone = '79991112233';
        $user->subscription = false;
        $user->save(false);

        $service = new UserSubscriptionService();
        $result = $service->update($user, true);

        verify($result['subscription'])->true();
        $user->refresh();
        verify((bool)$user->subscription)->true();
    }

    public function testRejectsNonBoolean(): void
    {
        $user = new User();
        $user->phone = '79991112244';
        $user->save(false);

        $service = new UserSubscriptionService();
        $this->expectException(ApiValidationException::class);
        $service->update($user, 'yes');
    }
}
