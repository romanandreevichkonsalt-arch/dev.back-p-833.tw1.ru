<?php

namespace app\modules\admin\models;

use app\models\AdminUser;
use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    public bool $rememberMe = true;

    private ?AdminUser $_user = null;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            [['username'], 'string', 'max' => 64],
            [['rememberMe'], 'boolean'],
            [['password'], 'validatePassword'],
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
        if ($user === null) {
            return false;
        }

        $duration = $this->rememberMe ? 3600 * 24 * 30 : 0;
        if (!Yii::$app->adminUser->login($user, $duration)) {
            return false;
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save(false, ['last_login_at']);

        return true;
    }

    private function getUser(): ?AdminUser
    {
        if ($this->_user === null) {
            $this->_user = AdminUser::findByUsername($this->username);
        }

        return $this->_user;
    }
}
