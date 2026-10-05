<?php

namespace tests\unit\services;

use app\models\MediaFile;
use app\models\Moodboard;
use app\services\moodboard\MoodboardBoardService;
use Codeception\Test\Unit;
use Yii;

class MoodboardBoardServiceCoverTest extends Unit
{
    private const SESSION_ID = 'unit-moodboard-cover-session';

    protected function _after(): void
    {
        if (Yii::$app->db->schema->getTableSchema('{{%moodboards}}', true) !== null) {
            Moodboard::deleteAll(['author_session_id' => self::SESSION_ID]);
        }
        parent::_after();
    }

    public function testSaveContentLinksCoverFromMediaId(): void
    {
        if (Yii::$app->db->schema->getTableSchema('{{%moodboards}}', true) === null) {
            $this->markTestSkipped('moodboards is not migrated in test DB.');
        }

        $media = new MediaFile();
        $media->filename = 'moodboard-cover-test.webp';
        $media->path = '/uploads/media/test/moodboard-cover-test.webp';
        $media->mime = 'image/webp';
        $media->size = 100;
        $media->kind = MediaFile::KIND_IMAGE;
        $media->created_at = date('Y-m-d H:i:s');
        verify($media->save(false))->true();

        $service = new MoodboardBoardService();
        $created = $service->createForSession(self::SESSION_ID, ['title' => 'Cover test']);
        verify($created['cover'])->null();

        $updated = $service->saveContentForSession(
            $created['id'],
            self::SESSION_ID,
            ['cover' => ['mediaId' => (int)$media->id]],
        );
        verify($updated['cover'])->notNull();
        verify($updated['cover']['src'] ?? null)->equals($media->path);

        $media->delete();
    }
}
