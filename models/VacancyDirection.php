<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class VacancyDirection extends ActiveRecord
{
    use AutoSlugTrait;

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    public static function tableName(): string
    {
        return '{{%vacancy_directions}}';
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
            [['slug', 'title'], 'required'],
            [['description'], 'string'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['number'], 'string', 'max' => 8],
            [['title', 'empty_title', 'empty_description'], 'string', 'max' => 255],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug (для API)',
            'number' => 'Номер',
            'title' => 'Направление',
            'description' => 'Описание',
            'empty_title' => 'Заголовок пустого блока',
            'empty_description' => 'Текст пустого блока',
            'sort_order' => 'Порядок',
            'is_active' => 'Активно',
        ];
    }

    public function getVacancies()
    {
        return $this->hasMany(Vacancy::class, ['direction_id' => 'id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiGroupPayload(): array
    {
        return array_filter([
            'id' => $this->slug,
            'number' => trim($this->number),
            'title' => trim($this->title),
            'description' => trim((string)$this->description),
            'emptyTitle' => trim($this->empty_title),
            'emptyDescription' => trim($this->empty_description),
        ], static fn (string $value): bool => $value !== '');
    }
}
