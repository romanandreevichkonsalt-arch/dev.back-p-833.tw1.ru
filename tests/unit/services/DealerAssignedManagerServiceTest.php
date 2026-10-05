<?php

namespace tests\unit\services;

use app\models\DealerManager;
use app\models\DealerProfile;
use app\models\User;
use app\services\dealer\DealerAssignedManagerService;
use Codeception\Test\Unit;
use Yii;

class DealerAssignedManagerServiceTest extends Unit
{
    private const USERNAME = 'unit-assigned-manager-dealer';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerProfile::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }
    }

    public function testUsesDefaultManagerFromParamsWhenNotAssigned(): void
    {
        $user = $this->createDealer(null);

        $manager = (new DealerAssignedManagerService())->resolveForUser($user);

        $this->assertNotNull($manager);
        $this->assertSame('Менеджер МФ Анна', $manager['name']);
        $this->assertSame('Менеджер заказов', $manager['role']);
    }

    public function testResolveNotificationEmailUsesAssignedManager(): void
    {
        $dealerManager = new DealerManager([
            'name' => 'Петрова Анна',
            'email' => 'anna@example.com',
            'is_active' => true,
        ]);
        $dealerManager->save(false);

        $user = $this->createDealer((int)$dealerManager->id);
        $email = (new DealerAssignedManagerService())->resolveNotificationEmailForUser($user);

        $this->assertSame('anna@example.com', $email);

        $dealerManager->delete();
    }

    public function testResolveNotificationEmailFallsBackToLeadsNotifyEmail(): void
    {
        $user = $this->createDealer(null);
        Yii::$app->params['leadsNotifyEmail'] = 'orders@example.com';

        $email = (new DealerAssignedManagerService())->resolveNotificationEmailForUser($user);

        $this->assertSame('orders@example.com', $email);
    }

    public function testUsesAssignedDealerManagerWhenSet(): void
    {
        $dealerManager = new DealerManager([
            'name' => 'Петрова Анна',
            'email' => 'anna@example.com',
            'phone' => '79991112233',
            'work_hours' => 'Пн–Пт 10:00–19:00',
            'role_label' => 'Старший менеджер',
            'is_active' => true,
        ]);
        $dealerManager->save(false);

        $user = $this->createDealer((int)$dealerManager->id);
        $manager = (new DealerAssignedManagerService())->resolveForUser($user);

        $this->assertSame('Петрова Анна', $manager['name']);
        $this->assertSame('Старший менеджер', $manager['role']);
        $this->assertSame('79991112233', $manager['phone']);

        $dealerManager->delete();
    }

    private function createDealer(?int $assignedManagerId): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990001122',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profile = new DealerProfile([
            'user_id' => (int)$user->id,
            'inn' => '7707083893',
            'company_name' => 'ООО Тест',
            'assigned_manager_id' => $assignedManagerId,
        ]);
        $profile->save(false);

        $user->refresh();

        return $user;
    }
}
