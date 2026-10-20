<?php

namespace app\modules\admin\models;

use app\models\DealerManager;
use app\models\DealerProfile;
use yii\base\Model;

class DealerForm extends Model
{
    public string $company_name = '';
    public ?string $manager_name = null;
    public string $inn = '';
    public ?int $assigned_manager_id = null;
    public ?string $email = null;
    public ?string $phone = null;
    public bool $send_email = true;
    public string $dealer_type = DealerProfile::TYPE_NEW;

    public function load($data, $formName = null): bool
    {
        if ($formName === null) {
            $formName = $this->formName();
        }

        if ($formName === '') {
            $this->normalizeRequestAttributes($data);
        } elseif (isset($data[$formName]) && is_array($data[$formName])) {
            $this->normalizeRequestAttributes($data[$formName]);
        }

        return parent::load($data, $formName);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeRequestAttributes(array &$row): void
    {
        if (array_key_exists('assigned_manager_id', $row)) {
            $value = $row['assigned_manager_id'];
            $row['assigned_manager_id'] = ($value === '' || $value === null) ? null : (int)$value;
        }

        if (array_key_exists('send_email', $row)) {
            $row['send_email'] = filter_var($row['send_email'], FILTER_VALIDATE_BOOLEAN);
        }
    }

    public function rules(): array
    {
        return [
            [['company_name'], 'required'],
            [['company_name', 'manager_name', 'email'], 'string', 'max' => 255],
            [['manager_name'], 'trim'],
            [
                ['assigned_manager_id'],
                'filter',
                'filter' => static fn ($value): ?int => ($value === '' || $value === null) ? null : (int)$value,
            ],
            [['assigned_manager_id'], 'integer', 'skipOnEmpty' => true],
            [
                ['assigned_manager_id'],
                'exist',
                'skipOnEmpty' => true,
                'targetClass' => DealerManager::class,
                'targetAttribute' => 'id',
                'filter' => ['is_active' => true],
            ],
            [['inn'], 'string', 'max' => 12],
            [['inn'], 'match', 'pattern' => '/^\d{10}(\d{2})?$/', 'skipOnEmpty' => true],
            [['phone'], 'string', 'max' => 20],
            [['email'], 'email'],
            [['send_email'], 'boolean'],
            [['dealer_type'], 'in', 'range' => [DealerProfile::TYPE_NEW, DealerProfile::TYPE_ACTIVE]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'company_name' => 'Наименование дилера',
            'manager_name' => 'ФИО дилера',
            'inn' => 'ИНН',
            'assigned_manager_id' => 'Менеджер фабрики',
            'email' => 'Email для доступа',
            'phone' => 'Телефон',
            'send_email' => 'Отправить доступ по email',
            'dealer_type' => 'Тип дилера',
        ];
    }
}
