<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DealerManager extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dealer_managers}}';
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
            [['name'], 'required'],
            [['name', 'email', 'role_label'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 20],
            [['work_hours'], 'string', 'max' => 255],
            [['email', 'phone', 'work_hours', 'role_label'], 'filter', 'filter' => static fn (?string $value): ?string => ($value = trim((string)$value)) !== '' ? $value : null],
            [['email'], 'email', 'skipOnEmpty' => true],
            [['is_active'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'ФИО',
            'phone' => 'Телефон',
            'email' => 'Email',
            'work_hours' => 'График работы',
            'role_label' => 'Должность',
            'is_active' => 'Активен',
        ];
    }

    public function getPublicRoleLabel(): string
    {
        if ($this->role_label !== null && trim($this->role_label) !== '') {
            return trim($this->role_label);
        }

        return 'Менеджер заказов';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAssignedManagerPayload(): array
    {
        return [
            'name' => (string)$this->name,
            'role' => $this->getPublicRoleLabel(),
            'phone' => $this->nullableString($this->phone),
            'email' => $this->nullableString($this->email),
            'hours' => $this->nullableString($this->work_hours),
            'avatar' => null,
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
