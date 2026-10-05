<?php

namespace tests\unit\services;

use app\models\DealerCashbackAccount;
use app\models\DealerProfile;
use app\models\User;
use app\services\dealer\DealerProfileService;
use Codeception\Test\Unit;
use Yii;

class DealerProfileServiceTest extends Unit
{
    private const USERNAME = 'unit-dealer-profile-service';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerCashbackAccount::deleteAll(['user_id' => (int)$user->id]);
            DealerProfile::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }
    }

    public function testPayloadIncludesCashbackWidget(): void
    {
        $profileSchema = Yii::$app->db->schema->getTableSchema('{{%dealer_profiles}}');
        if ($profileSchema === null || $profileSchema->getColumn('assigned_manager_id') === null) {
            $this->markTestSkipped('Test DB schema is missing dealer_profiles.assigned_manager_id.');
        }

        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990005566',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profile = new DealerProfile([
            'user_id' => (int)$user->id,
            'inn' => '7707083893',
            'company_name' => 'ООО Тест',
        ]);
        $profile->save(false);

        $account = new DealerCashbackAccount([
            'user_id' => (int)$user->id,
            'balance' => 12_500,
            'period_total' => 500_000,
            'period_year_month' => date('Y-m'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $account->save(false);

        $payload = (new DealerProfileService())->toPayload($user);

        $this->assertArrayHasKey('cashback', $payload);
        $this->assertSame(12_500.0, $payload['cashback']['balance']);
        $this->assertSame(500_000.0, $payload['cashback']['periodTotal']);
        $this->assertArrayHasKey('promotionBanners', $payload);
        $this->assertArrayHasKey('catalogPromotions', $payload);
        $this->assertIsArray($payload['promotionBanners']);
        $this->assertIsArray($payload['catalogPromotions']);
    }

    public function testUpdateMarksProfileCompleteAndReturnsAssignedManager(): void
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990005566',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profile = new DealerProfile([
            'user_id' => (int)$user->id,
            'inn' => '7707083893',
            'company_name' => 'ООО Тест',
        ]);
        $profile->save(false);

        $payload = (new DealerProfileService())->update($user, [
            'inn' => '7707083893',
            'managerName' => 'Иванов Иван',
            'email' => 'dealer@example.com',
            'phone' => '79990005566',
        ]);

        $this->assertTrue($payload['profileComplete']);
        $this->assertArrayHasKey('assignedManager', $payload);
        $this->assertNotNull($payload['assignedManager']);
        $this->assertSame('Менеджер МФ Анна', $payload['assignedManager']['name']);
    }
}
