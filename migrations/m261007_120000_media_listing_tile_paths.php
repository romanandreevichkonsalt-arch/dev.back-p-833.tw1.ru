<?php

use app\models\MediaFile;
use app\services\media\ListingTileLegacyMigrator;
use yii\db\Migration;

class m261007_120000_media_listing_tile_paths extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%media_files}}', 'path_listing_medium', $this->string(512)->null()->after('path_mini'));
        $this->addColumn('{{%media_files}}', 'path_listing_mini', $this->string(512)->null()->after('path_listing_medium'));

        $ids = (new \yii\db\Query())
            ->select('id')
            ->from('{{%media_files}}')
            ->where(['listing_frame_locked' => true])
            ->column($this->db);

        if ($ids === []) {
            return;
        }

        $migrator = new ListingTileLegacyMigrator();
        foreach (MediaFile::find()->where(['id' => $ids])->each() as $media) {
            /** @var MediaFile $media */
            try {
                $migrator->migrateLockedMedia($media);
            } catch (\Throwable $e) {
                echo 'Listing tile migrate #' . $media->id . ': ' . $e->getMessage() . PHP_EOL;
            }
        }
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%media_files}}', 'path_listing_mini');
        $this->dropColumn('{{%media_files}}', 'path_listing_medium');
    }
}
