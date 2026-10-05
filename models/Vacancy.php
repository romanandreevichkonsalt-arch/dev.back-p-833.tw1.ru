<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Vacancy extends ActiveRecord
{
    use AutoSlugTrait;

    public const SCHEDULE_FULL_TIME = 'Полный день';
    public const SCHEDULE_PART_TIME = 'Частичная занятость';
    public const SCHEDULE_HYBRID = 'Гибридный формат';

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    public static function tableName(): string
    {
        return '{{%vacancies}}';
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
            [['slug', 'direction_id', 'title'], 'required'],
            [['description', 'requirements', 'conditions', 'seo_description'], 'string'],
            [['slug'], 'string', 'max' => 128],
            [['slug'], 'unique'],
            [['direction_id', 'sort_order'], 'integer'],
            [['direction_id'], 'exist', 'skipOnError' => true, 'targetClass' => VacancyDirection::class, 'targetAttribute' => ['direction_id' => 'id']],
            [['title', 'salary', 'salary_mobile', 'department', 'schedule', 'location', 'meta', 'category_label', 'seo_title'], 'string', 'max' => 255],
            [['schedule'], 'in', 'range' => array_keys(self::scheduleOptions())],
            [['posted_at'], 'date', 'format' => 'php:Y-m-d'],
            [['is_active', 'show_on_about'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'slug' => 'Slug',
            'direction_id' => 'Направление',
            'title' => 'Должность',
            'description' => 'Описание',
            'salary' => 'Зарплата',
            'salary_mobile' => 'Зарплата (моб.)',
            'department' => 'Цех / подразделение',
            'schedule' => 'Занятость',
            'location' => 'Место работы',
            'meta' => 'Meta (устар., одной строкой)',
            'category_label' => 'Категория (тизер «О нас»)',
            'posted_at' => 'Дата публикации',
            'requirements' => 'Требования',
            'conditions' => 'Условия',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'sort_order' => 'Порядок',
            'show_on_about' => 'Показывать на «О нас»',
            'created_at' => 'Создана',
            'updated_at' => 'Обновлена',
        ];
    }

    public function getDirection()
    {
        return $this->hasOne(VacancyDirection::class, ['id' => 'direction_id']);
    }

    /**
     * @return array<int, string>
     */
    public static function directionLabels(): array
    {
        $labels = [];
        $directions = VacancyDirection::find()
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($directions as $direction) {
            $labels[(int)$direction->id] = $direction->title;
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public static function scheduleOptions(): array
    {
        return [
            self::SCHEDULE_FULL_TIME => self::SCHEDULE_FULL_TIME,
            self::SCHEDULE_PART_TIME => self::SCHEDULE_PART_TIME,
            self::SCHEDULE_HYBRID => self::SCHEDULE_HYBRID,
        ];
    }

    public function getDirectionLabel(): string
    {
        return $this->direction?->title ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function getRequirementsArray(): array
    {
        return self::decodeStringList($this->requirements);
    }

    /**
     * @param array<int, string> $items
     */
    public function setRequirementsArray(array $items): void
    {
        $this->requirements = self::encodeStringList($items);
    }

    /**
     * @return array<int, string>
     */
    public function getConditionsArray(): array
    {
        return self::decodeStringList($this->conditions);
    }

    /**
     * @param array<int, string> $items
     */
    public function setConditionsArray(array $items): void
    {
        $this->conditions = self::encodeStringList($items);
    }

    /**
     * @return array<int, string>
     */
    private static function decodeStringList(?string $raw): array
    {
        if ($raw === '' || $raw === null) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $item) {
            $text = trim((string)$item);
            if ($text !== '') {
                $result[] = $text;
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $items
     */
    private static function encodeStringList(array $items): string
    {
        $normalized = [];
        foreach ($items as $item) {
            $text = trim((string)$item);
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function beforeSave($insert): bool
    {
        $this->is_active = true;

        if ($this->requirements === '' || $this->requirements === null) {
            $this->requirements = '[]';
        }
        if ($this->conditions === '' || $this->conditions === null) {
            $this->conditions = '[]';
        }

        return parent::beforeSave($insert);
    }
}
