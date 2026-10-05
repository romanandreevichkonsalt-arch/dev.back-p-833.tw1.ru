<?php

namespace app\models;

use app\services\content\BlockTypeRegistry;
use app\services\content\HeroBannerPayload;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class ContentBlock extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%content_blocks}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['page_id', 'block_key', 'block_type', 'data'], 'required'],
            [['page_id', 'sort_order'], 'integer'],
            [['block_key', 'block_type'], 'string', 'max' => 64],
            [['data'], 'string'],
            [['is_active'], 'boolean'],
            [['block_key'], 'unique', 'targetAttribute' => ['page_id', 'block_key']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'block_key' => 'Ключ блока',
            'block_type' => 'Тип блока',
            'sort_order' => 'Порядок',
            'is_active' => 'Активен',
        ];
    }

    public function getPage()
    {
        return $this->hasOne(ContentPage::class, ['id' => 'page_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataArray(): array
    {
        $data = json_decode($this->data, true);
        if (!is_array($data)) {
            return [];
        }

        if ($this->isHeroBannerBlock()) {
            return HeroBannerPayload::normalize($data);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setDataArray(array $data): void
    {
        if ($this->isHeroBannerBlock()) {
            $data = HeroBannerPayload::normalize($data);
        }

        $this->data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function isHeroBannerBlock(): bool
    {
        return in_array($this->block_type, [
            BlockTypeRegistry::TYPE_HERO_MEDIA,
            BlockTypeRegistry::TYPE_HERO_HOME,
        ], true);
    }
}
