<?php

namespace tests\api;

use app\models\GuestSession;
use app\models\MediaFile;
use app\models\Moodboard;
use app\models\SmsCode;
use app\models\User;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class MoodboardApiTest extends ApiTestCase
{
    private const TEST_PHONE = '79894232002';
    private const SESSION_ID = 'guest-session-mood-01';

    protected function _before(): void
    {
        parent::_before();
        if (Yii::$app->db->schema->getTableSchema('{{%moodboards}}', true) === null) {
            $this->markTestSkipped('moodboards is not migrated in test DB.');
        }
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->request->headers->remove('X-Session-ID');
        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->setQueryParams([]);

        Moodboard::deleteAll(['author_session_id' => self::SESSION_ID]);
        GuestSession::deleteAll(['session_id' => self::SESSION_ID]);

        SmsCode::deleteAll(['phone' => self::TEST_PHONE]);
        $user = User::findByPhone(self::TEST_PHONE);
        if ($user !== null) {
            Moodboard::deleteAll(['author_user_id' => (int)$user->id]);
            $user->delete();
        }
    }

    public function testPickerBootstrapWithoutAuth(): void
    {
        $data = $this->getJson('api/v1/moodboard/picker/bootstrap');
        verify($data)->arrayHasKey('categories');
        verify($data)->arrayHasKey('objectTypes');
    }

    public function testGuestSaveCoverByMediaId(): void
    {
        $media = MediaFile::find()->orderBy(['id' => SORT_DESC])->one();
        if ($media === null) {
            $this->markTestSkipped('No media_files row in test DB.');
        }

        $this->withSession(self::SESSION_ID);
        $created = $this->runAction('api/v1/moodboard/create-board', [], [
            'title' => 'With cover ref',
        ], 'POST');
        verify($created)->notNull();
        verify($created['cover'])->null();

        $updated = $this->runAction('api/v1/moodboard/update-board', ['id' => $created['id']], [
            'cover' => ['mediaId' => (int)$media->id],
        ], 'PUT');
        verify($updated)->notNull();
        verify($updated['cover'])->notNull();
        verify($updated['cover']['src'] ?? null)->notEmpty();
    }

    public function testGuestCreatePublicViewAndList(): void
    {
        $this->withSession(self::SESSION_ID);

        $created = $this->runAction('api/v1/moodboard/create-board', [], [
            'title' => 'Guest board',
        ], 'POST');
        verify($created)->notNull();
        verify($created['title'])->equals('Guest board');
        verify($created['shareCode'])->notEmpty();
        verify($created['shareUrl'])->notEmpty();
        verify($created['publicApiUrl'])->stringContainsString('/api/v1/moodboard/public/');
        verify($created['author'])->null();

        $publicId = $created['id'];
        $shareCode = $created['shareCode'];

        Yii::$app->request->headers->remove('X-Session-ID');
        $public = $this->runAction('api/v1/moodboard/view-public-board', ['code' => $shareCode]);
        verify($public['id'])->equals($publicId);
        verify($public['title'])->equals('Guest board');

        $this->withSession(self::SESSION_ID);
        $list = $this->getJson('api/v1/moodboard/list-boards');
        verify($list['meta']['total'])->equals(1);
        verify($list['items'][0]['shareCode'])->equals($shareCode);
    }

    public function testGuestBoardRequiresSessionForCreate(): void
    {
        $this->expectException(UnauthorizedHttpException::class);
        $this->runAction('api/v1/moodboard/create-board', [], ['title' => 'No session'], 'POST');
    }

    public function testPublicViewUnknownShareCode(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->runAction('api/v1/moodboard/view-public-board', ['code' => 'deadbeefdeadbeefdeadbeef']);
    }

    public function testAuthMergesGuestMoodboards(): void
    {
        $this->withSession(self::SESSION_ID);
        $created = $this->runAction('api/v1/moodboard/create-board', [], [
            'title' => 'Merge me',
        ], 'POST');
        verify($created)->notNull();
        $shareCode = $created['shareCode'];
        $publicId = $created['id'];

        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->user->setIdentity(null);

        $this->postJson('api/v1/auth/request-code', ['phone' => self::TEST_PHONE]);
        $smsCode = SmsCode::find()
            ->where(['phone' => self::TEST_PHONE, 'used_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        verify($smsCode)->notNull();

        $this->withSession(self::SESSION_ID);
        $tokenResponse = $this->postJson('api/v1/auth/verify-code', [
            'phone' => self::TEST_PHONE,
            'code' => $smsCode->code,
            'sessionId' => self::SESSION_ID,
        ]);

        verify($tokenResponse['guestSync']['skipped'])->false();
        verify($tokenResponse['guestSync']['moodboards']['mergedCount'])->equals(1);

        Yii::$app->request->headers->remove('X-Session-ID');
        $public = $this->runAction('api/v1/moodboard/view-public-board', ['code' => $shareCode]);
        verify($public['id'])->equals($publicId);

        $user = User::findByPhone(self::TEST_PHONE);
        verify($user)->notNull();
        verify(Moodboard::find()->where(['author_session_id' => self::SESSION_ID])->count())->equals(0);
        verify(Moodboard::find()->where(['author_user_id' => (int)$user->id, 'public_id' => $publicId])->count())->equals(1);
    }
}
