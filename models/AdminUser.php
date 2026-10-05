<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class AdminUser extends ActiveRecord implements IdentityInterface
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_CATALOG = 'catalog_manager';

    public static function tableName(): string
    {
        return '{{%admin_users}}';
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
            [['username', 'password_hash', 'auth_key', 'name', 'role'], 'required'],
            [['username'], 'string', 'max' => 64],
            [['username'], 'unique'],
            [['email'], 'email'],
            [['email', 'name'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 20],
            [['work_hours', 'public_role'], 'string', 'max' => 255],
            [['role'], 'in', 'range' => [
                self::ROLE_ADMIN,
                self::ROLE_EDITOR,
                self::ROLE_MANAGER,
                self::ROLE_CATALOG,
            ]],
            [['is_active'], 'boolean'],
            [['last_login_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Логин',
            'email' => 'Email',
            'name' => 'Имя',
            'phone' => 'Телефон',
            'work_hours' => 'Часы работы',
            'public_role' => 'Должность для ЛКД',
            'role' => 'Роль',
            'is_active' => 'Активен',
            'last_login_at' => 'Последний вход',
            'created_at' => 'Создан',
        ];
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'is_active' => true]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    public static function findByUsername(string $username): ?self
    {
        return static::findOne(['username' => $username, 'is_active' => true]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    public function validatePassword(string $password): bool
    {
        return \Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = \Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = \Yii::$app->security->generateRandomString();
    }

    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_EDITOR => 'Редактор контента',
            self::ROLE_MANAGER => 'Менеджер',
            self::ROLE_CATALOG => 'Каталог-менеджер',
        ];
    }

    public function getRoleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? $this->role;
    }

    public function getPublicRoleLabel(): string
    {
        if ($this->public_role !== null && trim($this->public_role) !== '') {
            return trim($this->public_role);
        }

        return match ($this->role) {
            self::ROLE_MANAGER => 'Менеджер заказов',
            self::ROLE_ADMIN => 'Администратор',
            default => 'Менеджер',
        };
    }

    public function canAccess(string $permission): bool
    {
        $map = [
            'dashboard' => [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_MANAGER, self::ROLE_CATALOG],
            'media' => [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_CATALOG],
            'leads' => [self::ROLE_ADMIN, self::ROLE_MANAGER],
            'orders' => [self::ROLE_ADMIN, self::ROLE_MANAGER],
            'catalog' => [self::ROLE_ADMIN, self::ROLE_CATALOG],
            'pages' => [self::ROLE_ADMIN, self::ROLE_EDITOR],
            'search' => [self::ROLE_ADMIN, self::ROLE_EDITOR],
            'users' => [self::ROLE_ADMIN],
            'settings' => [self::ROLE_ADMIN],
        ];

        return in_array($this->role, $map[$permission] ?? [], true);
    }
}
