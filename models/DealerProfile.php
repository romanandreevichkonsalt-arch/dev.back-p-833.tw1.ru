<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DealerProfile extends ActiveRecord
{
    public const TYPE_NEW = 'new';
    public const TYPE_ACTIVE = 'active';

    public static function tableName(): string
    {
        return '{{%dealer_profiles}}';
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
            [['user_id', 'company_name'], 'required'],
            [['user_id', 'created_by_admin_id', 'assigned_manager_id'], 'integer'],
            [['personal_discount_percent'], 'number', 'min' => 0, 'max' => 100],
            [['cashback_expiry_days'], 'integer', 'min' => 1, 'max' => 3650],
            [['inn'], 'string', 'max' => 12],
            [['inn'], 'match', 'pattern' => '/^\d{10}(\d{2})?$/', 'skipOnEmpty' => true],
            [['inn'], 'unique', 'skipOnEmpty' => true],
            [['company_name', 'manager_name', 'email'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['dealer_type'], 'in', 'range' => [self::TYPE_NEW, self::TYPE_ACTIVE]],
            [
                ['assigned_manager_id'],
                'exist',
                'skipOnEmpty' => true,
                'targetClass' => DealerManager::class,
                'targetAttribute' => 'id',
                'filter' => ['is_active' => true],
            ],
            [['credentials_sent_at', 'first_login_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->assigned_manager_id === '' || $this->assigned_manager_id === 0) {
            $this->assigned_manager_id = null;
        }

        return parent::beforeValidate();
    }

    public function attributeLabels(): array
    {
        return [
            'inn' => 'ИНН',
            'company_name' => 'Наименование дилера',
            'manager_name' => 'ФИО дилера',
            'email' => 'Email',
            'dealer_type' => 'Тип дилера',
            'personal_discount_percent' => 'Персональная скидка, %',
            'cashback_expiry_days' => 'Срок кэшбека, дней',
            'assigned_manager_id' => 'Менеджер фабрики',
            'credentials_sent_at' => 'Доступ отправлен',
            'first_login_at' => 'Первый вход',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreatedByAdmin()
    {
        return $this->hasOne(AdminUser::class, ['id' => 'created_by_admin_id']);
    }

    public function getAssignedManager()
    {
        return $this->hasOne(DealerManager::class, ['id' => 'assigned_manager_id']);
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_NEW => 'Новый',
            self::TYPE_ACTIVE => 'Действующий',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->dealer_type] ?? $this->dealer_type;
    }

    public function isProfileComplete(User $user): bool
    {
        return trim((string)$this->inn) !== ''
            && $this->manager_name !== null
            && trim($this->manager_name) !== ''
            && $this->email !== null
            && trim($this->email) !== ''
            && $user->phone !== null
            && preg_match('/^7\d{10}$/', (string)$user->phone) === 1;
    }
}
