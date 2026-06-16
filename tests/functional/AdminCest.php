<?php

use app\models\User;
use app\services\RbacInitializer;
use Yii;

class AdminCest
{
    public function _before(FunctionalTester $I): void
    {
        $this->ensureRbac();
    }

    public function guestRedirectedToLogin(FunctionalTester $I): void
    {
        $I->amOnPage('/index-test.php/admin/default/index');
        $I->seeCurrentUrlEquals('/index-test.php/admin/auth/login');
        $I->see('Вход в админку');
    }

    public function loginWithWrongPassword(FunctionalTester $I): void
    {
        $I->amOnPage('/index-test.php/admin/auth/login');
        $I->submitForm('#login-form', [
            'AdminLoginForm[username]' => 'admin',
            'AdminLoginForm[password]' => 'wrong-password',
        ]);
        $I->see('Неверный логин или пароль');
    }

    public function loginAndViewDashboard(FunctionalTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->see('Дашборд');
        $I->see('Категории');
    }

    public function categoriesCrud(FunctionalTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/index-test.php/admin/category/index');
        $I->see('Категории', 'h1');

        $I->amOnPage('/index-test.php/admin/category/create');
        $I->submitForm('#category-form', [
            'Category[name]' => 'Тестовая категория',
            'Category[slug]' => 'test-category',
            'Category[is_active]' => '1',
        ]);
        $I->see('Тестовая категория');
    }

    private function loginAsAdmin(FunctionalTester $I): void
    {
        $I->amOnPage('/index-test.php/admin/auth/login');
        $I->submitForm('#login-form', [
            'AdminLoginForm[username]' => 'admin',
            'AdminLoginForm[password]' => 'admin123',
        ]);
        $I->see('Дашборд');
    }

    private function ensureRbac(): void
    {
        $initializer = new RbacInitializer();
        $initializer->init(Yii::$app->authManager);

        $user = User::findAdminByUsername('admin');
        if ($user === null) {
            $user = new User([
                'phone' => '79990000002',
                'username' => 'admin',
            ]);
            $user->setPassword('admin123');
            $user->generateAuthKey();
            $user->save(false);
        }

        $initializer->assignRole(Yii::$app->authManager, (int) $user->id, RbacInitializer::ROLE_ADMIN);
    }
}
