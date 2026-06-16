<?php

namespace app\commands;

use app\models\User;
use app\services\RbacInitializer;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class RbacController extends Controller
{
    public string $adminUsername = 'admin';
    public string $adminPassword = 'admin123';
    public string $adminPhone = '79990000001';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['adminUsername', 'adminPassword', 'adminPhone']);
    }

    public function actionInit(): int
    {
        $initializer = new RbacInitializer();
        $initializer->init(Yii::$app->authManager);
        $this->stdout("RBAC initialized.\n", Console::FG_GREEN);

        $user = User::findAdminByUsername($this->adminUsername);
        if ($user === null) {
            $user = new User([
                'phone' => $this->adminPhone,
                'username' => $this->adminUsername,
            ]);
            $user->setPassword($this->adminPassword);
            $user->generateAuthKey();
            if (!$user->save()) {
                $this->stderr("Failed to create admin user.\n", Console::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("Admin user created: {$this->adminUsername}\n", Console::FG_GREEN);
        } else {
            $this->stdout("Admin user exists: {$this->adminUsername}\n", Console::FG_YELLOW);
        }

        $auth = Yii::$app->authManager;
        if ($auth->getRolesByUser((string) $user->id) === []) {
            $initializer->assignRole($auth, (int) $user->id, RbacInitializer::ROLE_ADMIN);
            $this->stdout("Role 'admin' assigned to user #{$user->id}.\n", Console::FG_GREEN);
        } else {
            $this->stdout("User #{$user->id} already has roles.\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }
}
