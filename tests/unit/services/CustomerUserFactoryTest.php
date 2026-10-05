<?php

namespace tests\unit\services;

use app\models\User;
use app\models\UserProfile;
use app\services\dealer\CustomerUserFactory;
use Codeception\Test\Unit;
use Yii;

class CustomerUserFactoryTest extends Unit
{
    private const PHONE_A = '79991111111';
    private const PHONE_B = '79992222222';
    private const PHONE_C = '79993333333';
    private const PHONE_D = '79994444444';
    private const PHONE_E = '79995556677';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();
        User::deleteAll(['phone' => [self::PHONE_A, self::PHONE_B, self::PHONE_C, self::PHONE_D, self::PHONE_E]]);
        User::deleteAll(['username' => [
            'Same Name',
            'Same Name · 1111',
            'customer-' . self::PHONE_B,
            'Order Client',
            'dealer-phone-conflict-test',
        ]]);
    }

    protected function _after(): void
    {
        User::deleteAll(['phone' => [self::PHONE_A, self::PHONE_B, self::PHONE_C, self::PHONE_D, self::PHONE_E]]);
        parent::_after();
    }

    public function testDuplicateDisplayNameGetsUniqueUsername(): void
    {
        $factory = new CustomerUserFactory();
        $first = $factory->findOrCreateFromOrderData('Same Name', '+7' . substr(self::PHONE_A, 1), null);
        $second = $factory->findOrCreateFromOrderData('Same Name', '+7' . substr(self::PHONE_B, 1), null);

        $this->assertSame('Same Name', $first->username);
        $this->assertNotSame($first->username, $second->username);
        $this->assertSame(self::PHONE_B, $second->phone);
    }

    public function testStoresCustomerEmailInProfileOnCreate(): void
    {
        $factory = new CustomerUserFactory();
        $user = $factory->findOrCreateFromOrderData(
            'Order Client',
            '+7' . substr(self::PHONE_C, 1),
            'order-client@example.com',
        );

        $profile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
        $this->assertNotNull($profile);
        $this->assertSame('order-client@example.com', $profile->email);
        $this->assertSame('Order Client', $profile->display_name);
    }

    public function testUpdatesEmailOnRepeatOrderByPhone(): void
    {
        $factory = new CustomerUserFactory();
        $user = $factory->findOrCreateFromOrderData(
            'Order Client',
            '+7' . substr(self::PHONE_C, 1),
            'first@example.com',
        );
        $factory->findOrCreateFromOrderData(
            'Order Client',
            '+7' . substr(self::PHONE_C, 1),
            'second@example.com',
        );

        $profile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
        $this->assertNotNull($profile);
        $this->assertSame('second@example.com', $profile->email);
    }

    public function testGuestOrderUpgradesSmsUserAndStoresEmail(): void
    {
        $smsUser = new User([
            'phone' => self::PHONE_D,
            'username' => self::PHONE_D,
        ]);
        $smsUser->save(false);

        $factory = new CustomerUserFactory();
        $user = $factory->findOrCreateFromOrderData(
            'Гость из корзины',
            '+7' . substr(self::PHONE_D, 1),
            'guest-checkout@example.com',
        );

        $this->assertSame((int)$smsUser->id, (int)$user->id);
        $user->refresh();
        $this->assertSame(User::TYPE_CUSTOMER, $user->type);

        $profile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
        $this->assertNotNull($profile);
        $this->assertSame('guest-checkout@example.com', $profile->email);
    }

    public function testDoesNotAttachDealerWhenPhoneMatches(): void
    {
        $dealer = new User([
            'type' => User::TYPE_DEALER,
            'phone' => self::PHONE_E,
            'username' => 'dealer-phone-conflict-test',
        ]);
        $dealer->save(false);

        $factory = new CustomerUserFactory();
        $client = $factory->findOrCreateFromOrderData(
            'Guest Client',
            '+7' . substr(self::PHONE_E, 1),
            'guest-conflict@example.com',
        );

        $this->assertNull($client);
        $dealer->refresh();
        $this->assertSame(User::TYPE_DEALER, $dealer->type);
        $this->assertNull(UserProfile::find()->where(['email' => 'guest-conflict@example.com'])->one());
    }
}
