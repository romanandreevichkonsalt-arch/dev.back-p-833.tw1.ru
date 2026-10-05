<?php

namespace app\models;

use yii\db\ActiveRecord;

class Lead extends ActiveRecord
{
    public const TYPE_CONTACTS = 'contacts';
    public const TYPE_FAQ = 'faq';
    public const TYPE_PARTNERS = 'partners';
    public const TYPE_DESIGNERS = 'designers';
    public const TYPE_VACANCY = 'vacancy';

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REJECTED = 'rejected';

    public static function tableName(): string
    {
        return '{{%leads}}';
    }

    public function rules(): array
    {
        return [
            [['type', 'name', 'consent'], 'required'],
            [['comment', 'manager_comment'], 'string'],
            [['consent'], 'boolean'],
            [['consent'], 'compare', 'compareValue' => true, 'message' => 'Необходимо согласие на обработку данных.'],
            [['type'], 'in', 'range' => array_keys(self::typeLabels())],
            [['status'], 'in', 'range' => array_keys(self::statusLabels())],
            [['name', 'studio', 'city', 'vacancy_slug', 'vacancy_title', 'resume_name', 'attachment_original_name'], 'string', 'max' => 255],
            [['attachment_path'], 'string', 'max' => 512],
            [['email'], 'email'],
            [['email'], 'string', 'max' => 255],
            [['phone'], 'match', 'pattern' => '/^\+7\d{10}$/', 'message' => 'Телефон должен быть в формате E.164 (+7…).', 'skipOnEmpty' => true],
            [['phone'], 'required', 'when' => static fn (self $model): bool => $model->type !== self::TYPE_VACANCY],
            [['email'], 'required', 'when' => static fn (self $model): bool => $model->type === self::TYPE_VACANCY],
            [['vacancy_slug', 'vacancy_title'], 'required', 'when' => static fn (self $model): bool => $model->type === self::TYPE_VACANCY],
            [['portfolio'], 'string', 'max' => 1024],
            [['portfolio'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
            [['amount'], 'number', 'min' => 0],
            [['assigned_to'], 'integer'],
            [['processed_at', 'created_at'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $common = ['type', 'name', 'email', 'phone', 'consent'];

        $scenarios[self::TYPE_CONTACTS] = array_merge($common, ['comment']);
        $scenarios[self::TYPE_FAQ] = array_merge($common, ['comment']);
        $scenarios[self::TYPE_PARTNERS] = array_merge($common, ['comment']);
        $scenarios[self::TYPE_DESIGNERS] = array_merge($common, ['studio', 'portfolio', 'city']);
        $scenarios[self::TYPE_VACANCY] = array_merge($common, ['comment', 'vacancy_slug', 'vacancy_title', 'resume_name']);
        $scenarios['admin'] = ['status', 'manager_comment', 'assigned_to', 'processed_at', 'amount'];

        return $scenarios;
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'type' => 'Тип',
            'name' => 'ФИО',
            'email' => 'Email',
            'phone' => 'Телефон',
            'comment' => 'Комментарий клиента',
            'consent' => 'Согласие',
            'status' => 'Статус',
            'manager_comment' => 'Комментарий менеджера',
            'assigned_to' => 'Ответственный',
            'processed_at' => 'Обработана',
            'studio' => 'Студия',
            'portfolio' => 'Ссылка на портфолио',
            'city' => 'Город',
            'vacancy_slug' => 'Slug вакансии',
            'vacancy_title' => 'Вакансия',
            'resume_name' => 'Имя файла резюме',
            'attachment_path' => 'Файл (путь)',
            'attachment_original_name' => 'Файл',
            'amount' => 'Сумма',
            'created_at' => 'Дата создания',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->scenario === 'admin') {
            return ActiveRecord::beforeValidate();
        }

        if (!empty($this->type)) {
            $this->setScenario($this->type);
        }

        return parent::beforeValidate();
    }

    public function getAssignee()
    {
        return $this->hasOne(AdminUser::class, ['id' => 'assigned_to']);
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CONTACTS => 'Контакты',
            self::TYPE_FAQ => 'FAQ',
            self::TYPE_PARTNERS => 'Партнёры',
            self::TYPE_DESIGNERS => 'Дизайнеры',
            self::TYPE_VACANCY => 'Вакансия',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW => 'Новая',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_CLOSED => 'Закрыта',
            self::STATUS_REJECTED => 'Отклонена',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS], true);
    }

    public function getFormattedAmount(): string
    {
        if ($this->amount === null || $this->amount === '') {
            return '—';
        }

        return number_format((float)$this->amount, 0, '.', ' ') . ' ₽';
    }

    public function hasStoredAttachment(): bool
    {
        return $this->attachment_path !== null && trim((string)$this->attachment_path) !== '';
    }

    public function getAttachmentDisplayName(): ?string
    {
        $name = trim((string)($this->attachment_original_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $legacy = trim((string)($this->resume_name ?? ''));

        return $legacy !== '' ? $legacy : null;
    }
}
