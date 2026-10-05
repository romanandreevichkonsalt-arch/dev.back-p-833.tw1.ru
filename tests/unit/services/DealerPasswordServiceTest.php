<?php

namespace tests\unit\services;

use app\models\User;
use app\services\dealer\DealerPasswordService;
use app\exceptions\ApiValidationException;
use Codeception\Test\Unit;
use Yii;

class DealerPasswordServiceTest extends Unit
{
    private const USERNAME = 'unit-dealer-password';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            $user->delete();
        }
    }

    public function testChangePasswordSuccess(): void
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990007788',
        ]);
        $user->setPassword('old-secret-1');
        $user->save(false);

        (new DealerPasswordService())->change($user, [
            'currentPassword' => 'old-secret-1',
            'newPassword' => 'new-secret-9',
        ]);

        $user->refresh();
        $this->assertTrue($user->validatePassword('new-secret-9'));
    }

    public function testChangePasswordRejectsWrongCurrent(): void
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990007788',
        ]);
        $user->setPassword('old-secret-1');
        $user->save(false);

        $this->expectException(ApiValidationException::class);
        (new DealerPasswordService())->change($user, [
            'currentPassword' => 'wrong',
            'newPassword' => 'new-secret-9',
        ]);
    }
}
