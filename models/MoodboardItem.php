<?php

namespace app\models;

use yii\db\ActiveRecord;

class MoodboardItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%moodboard_items}}';
    }

    public function rules(): array
    {
        return [
            [['moodboard_id', 'object_type', 'ref_id', 'ref_slug', 'width', 'height', 'x', 'y'], 'required'],
            [['moodboard_id', 'ref_id', 'z_index', 'sort_order'], 'integer'],
            [['object_type'], 'string', 'max' => 32],
            [['ref_slug'], 'string', 'max' => 128],
            [['width', 'height', 'x', 'y', 'rotation'], 'number'],
            [['payload_json'], 'string'],
            [['created_at'], 'safe'],
            [['object_type'], 'exist', 'targetClass' => MoodboardObjectType::class, 'targetAttribute' => ['object_type' => 'code']],
            [['moodboard_id'], 'exist', 'targetClass' => Moodboard::class, 'targetAttribute' => ['moodboard_id' => 'id']],
        ];
    }

    public function getMoodboard()
    {
        return $this->hasOne(Moodboard::class, ['id' => 'moodboard_id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayloadArray(): ?array
    {
        if ($this->payload_json === null || trim($this->payload_json) === '') {
            return null;
        }

        $decoded = json_decode($this->payload_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function setPayloadArray(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            $this->payload_json = null;

            return;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->payload_json = $encoded !== false ? $encoded : null;
    }
}
