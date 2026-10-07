<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class JournalArticle extends ActiveRecord
{
    use AutoSlugTrait;

    public const CATEGORY_PROCESS = 'process';
    public const CATEGORY_INTERIOR = 'interior';
    public const CATEGORY_TEXTURES = 'textures';
    public const CATEGORY_INTERVIEWS = 'interviews';

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    public static function tableName(): string
    {
        return '{{%journal_articles}}';
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
            [['slug', 'category_id', 'title'], 'required'],
            [['excerpt', 'seo_description', 'blocks', 'body_markdown'], 'string'],
            [['slug'], 'string', 'max' => 128],
            [['slug'], 'unique'],
            [['category_id'], 'string', 'max' => 32],
            [['category_id'], 'in', 'range' => array_keys(self::categoryLabels())],
            [['title', 'image_alt', 'seo_title'], 'string', 'max' => 255],
            [['date'], 'string', 'max' => 16],
            [['reading_time'], 'string', 'max' => 32],
            [['image_src'], 'string', 'max' => 1024],
            [['image_position'], 'string', 'max' => 64],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'slug' => 'Slug',
            'category_id' => 'Категория',
            'title' => 'Заголовок',
            'date' => 'Дата',
            'reading_time' => 'Время чтения',
            'excerpt' => 'Краткое описание',
            'image_src' => 'Превью',
            'image_alt' => 'Alt превью',
            'image_position' => 'Позиция фото',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'blocks' => 'Контент',
            'body_markdown' => 'Текст статьи (Markdown)',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
            'created_at' => 'Создана',
            'updated_at' => 'Обновлена',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_PROCESS => 'Архитектура процессов',
            self::CATEGORY_INTERIOR => 'Интерьерные проекты',
            self::CATEGORY_TEXTURES => 'Исследование фактур',
            self::CATEGORY_INTERVIEWS => 'Интервью с дизайнерами и командой',
        ];
    }

    public function getCategoryLabel(): string
    {
        return self::categoryLabels()[$this->category_id] ?? $this->category_id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocksArray(): array
    {
        if ($this->blocks === '' || $this->blocks === null) {
            return [];
        }

        $data = json_decode($this->blocks, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public function setBlocksArray(array $blocks): void
    {
        $this->blocks = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function beforeSave($insert): bool
    {
        if ($this->blocks === '' || $this->blocks === null) {
            $this->blocks = '[]';
        }

        if ($this->body_markdown === null) {
            $this->body_markdown = '';
        }

        return parent::beforeSave($insert);
    }
}
