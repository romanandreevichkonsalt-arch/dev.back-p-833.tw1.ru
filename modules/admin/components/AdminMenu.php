<?php

namespace app\modules\admin\components;

use app\services\RbacInitializer;
use Yii;
use yii\bootstrap5\Nav;

class AdminMenu
{
    public static function items(): array
    {
        $items = [
            ['label' => 'Дашборд', 'url' => ['/admin/default/index']],
        ];

        if (Yii::$app->user->can(RbacInitializer::PERM_MANAGE_CATEGORIES)) {
            $items[] = ['label' => 'Категории', 'url' => ['/admin/category/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_MANAGE_PRODUCTS)) {
            $items[] = ['label' => 'Товары', 'url' => ['/admin/product/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_MANAGE_ATTRIBUTES)) {
            $items[] = ['label' => 'Атрибуты', 'url' => ['/admin/attribute-definition/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_MANAGE_USERS)) {
            $items[] = ['label' => 'Пользователи', 'url' => ['/admin/user/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_MANAGE_ROLES)) {
            $items[] = ['label' => 'Роли', 'url' => ['/admin/rbac/index']];
        }

        $system = [];
        if (Yii::$app->user->can(RbacInitializer::PERM_VIEW_TOKENS)) {
            $system[] = ['label' => 'API-токены', 'url' => ['/admin/api-access-token/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_VIEW_EXTERNAL_IDENTITIES)) {
            $system[] = ['label' => 'Yandex ID', 'url' => ['/admin/external-identity/index']];
        }
        if (Yii::$app->user->can(RbacInitializer::PERM_VIEW_SMS_CODES)) {
            $system[] = ['label' => 'SMS-коды', 'url' => ['/admin/sms-code/index']];
        }
        if ($system !== []) {
            $items[] = ['label' => 'Система', 'items' => $system];
        }

        return $items;
    }

    public static function widget(): string
    {
        return Nav::widget([
            'options' => ['class' => 'nav flex-column'],
            'items' => self::items(),
        ]);
    }
}
