<?php

namespace app\models;

use yii\db\ActiveRecord;

class MoodboardComment extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%moodboard_comments}}';
    }

    public function rules(): array
    {
        return [
            [['moodboard_id', 'text', 'x', 'y'], 'required'],
            [['moodboard_id', 'author_user_id'], 'integer'],
            [['text'], 'string', 'max' => 2000],
            [['x', 'y'], 'number'],
            [['created_at'], 'safe'],
            [['moodboard_id'], 'exist', 'targetClass' => Moodboard::class, 'targetAttribute' => ['moodboard_id' => 'id']],
        ];
    }

    public function getMoodboard()
    {
        return $this->hasOne(Moodboard::class, ['id' => 'moodboard_id']);
    }
}
