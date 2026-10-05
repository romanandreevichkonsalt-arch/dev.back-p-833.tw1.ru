<?php

namespace app\services\dealer;

use app\models\User;

class DealerCredentialGenerator
{
    public function generateUsername(?string $inn = null): string
    {
        $digits = preg_replace('/\D+/', '', (string)$inn) ?? '';
        if ($digits !== '') {
            $base = 'd' . $digits;
        } else {
            $base = 'd' . strtolower(substr(str_replace(['_', '-'], '', \Yii::$app->security->generateRandomString(12)), 0, 8));
        }

        $username = $base;
        $suffix = 1;

        while (User::find()->where(['username' => $username])->exists()) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    public function generatePassword(int $length = 12): string
    {
        return \Yii::$app->security->generateRandomString($length);
    }
}
