<?php

namespace app\modules\admin\models;

use app\models\User;
use Yii;
use yii\base\Model;

class AdminLoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    public bool $rememberMe = true;

    private ?User $_user = null;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            [['username', 'password'], 'string'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Логин',
            'password' => 'Пароль',
            'rememberMe' => 'Запомнить меня',
        ];
    }

    public function validatePassword(string $attribute): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = $this->getUser();
        if ($user === null || !$user->validatePassword($this->password)) {
            $this->addError($attribute, 'Неверный логин или пароль.');
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = $this->getUser();
        if ($user === null || !$user->hasAdminAccess()) {
            $this->addError('password', 'У пользователя нет роли для доступа в админку.');

            return false;
        }

        return Yii::$app->user->login($user, $this->rememberMe ? 3600 * 24 * 30 : 0);
    }

    public function getUser(): ?User
    {
        if ($this->_user === null) {
            $this->_user = User::findAdminByUsername($this->username);
        }

        return $this->_user;
    }
}
