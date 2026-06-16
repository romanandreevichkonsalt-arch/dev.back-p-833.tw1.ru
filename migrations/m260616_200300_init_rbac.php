<?php

use app\models\User;
use app\services\RbacInitializer;
use yii\db\Migration;

class m260616_200300_init_rbac extends Migration
{
    public function safeUp(): void
    {
        $initializer = new RbacInitializer();
        $initializer->init(Yii::$app->authManager);

        $user = User::findAdminByUsername('admin');
        if ($user === null) {
            $user = new User([
                'phone' => '79990000001',
                'username' => 'admin',
            ]);
            $user->setPassword('admin123');
            $user->generateAuthKey();
            if (!$user->save()) {
                throw new \RuntimeException('Failed to create default admin user.');
            }
        }

        $initializer->assignRole(Yii::$app->authManager, (int) $user->id, RbacInitializer::ROLE_ADMIN);
    }

    public function safeDown(): void
    {
        Yii::$app->authManager->removeAll();
    }
}
