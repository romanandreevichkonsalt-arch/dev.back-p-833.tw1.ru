<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogImportMediaQueue extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FOLDER = 'folder';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%catalog_import_media_queue}}';
    }

    public function rules(): array
    {
        return [
            [['import_run_id', 'row_number', 'source_url', 'status', 'created_at'], 'required'],
            [['import_run_id', 'row_number', 'media_file_id', 'entity_id'], 'integer'],
            [['source_url'], 'string', 'max' => 512],
            [['url_type', 'status', 'entity_type'], 'string', 'max' => 64],
            [['resolved_filename'], 'string', 'max' => 255],
            [['error_message'], 'string'],
            [['created_at'], 'safe'],
        ];
    }
}
