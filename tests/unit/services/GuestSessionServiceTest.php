<?php

namespace tests\unit\services;

use app\models\GuestSession;
use app\services\guest\GuestSessionService;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;

class GuestSessionServiceTest extends Unit
{
    private const SESSION_ID = 'unit-guest-ensure-01';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();
        GuestSession::deleteAll(['session_id' => self::SESSION_ID]);
    }

    public function testEnsureCreatesSessionOnce(): void
    {
        $service = new GuestSessionService();

        $service->ensure(self::SESSION_ID);

        verify(GuestSession::find()->where(['session_id' => self::SESSION_ID])->count())->equals(1);
    }

    public function testEnsureUpdatesExistingSessionWithoutDuplicate(): void
    {
        $service = new GuestSessionService();
        $service->ensure(self::SESSION_ID);

        $before = GuestSession::findOne(['session_id' => self::SESSION_ID]);
        verify($before)->notNull();
        $createdAt = $before->created_at;
        $before->updated_at = '2020-01-01 00:00:00';
        $before->save(false, ['updated_at']);

        $service->ensure(self::SESSION_ID);

        $after = GuestSession::findOne(['session_id' => self::SESSION_ID]);
        verify($after)->notNull();
        verify(GuestSession::find()->where(['session_id' => self::SESSION_ID])->count())->equals(1);
        verify($after->created_at)->equals($createdAt);
        verify($after->updated_at)->notEquals('2020-01-01 00:00:00');
    }

    public function testEnsureDoesNotFailWhenRowAlreadyExists(): void
    {
        $now = date('Y-m-d H:i:s');
        Yii::$app->db->createCommand()->insert('{{%guest_sessions}}', [
            'session_id' => self::SESSION_ID,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $service = new GuestSessionService();
        $service->ensure(self::SESSION_ID);

        verify(GuestSession::find()->where(['session_id' => self::SESSION_ID])->count())->equals(1);
    }

    public function testNormalizeRejectsInvalidSessionId(): void
    {
        $service = new GuestSessionService();

        $this->expectException(BadRequestHttpException::class);
        $service->normalize('bad id!');
    }
}
