<?php

use app\models\AdminUser;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */

$controller = Yii::$app->controller;
$route = $controller->module->id . '/' . $controller->id . '/' . $controller->action->id;

$items = [
    ['label' => 'Дашборд', 'url' => ['/admin/dashboard/index'], 'permission' => 'dashboard', 'controllers' => ['dashboard']],
    ['label' => 'Заявки', 'url' => ['/admin/lead/index'], 'permission' => 'leads', 'controllers' => ['lead']],
    ['label' => 'Заказы', 'url' => ['/admin/order/index'], 'permission' => 'orders', 'controllers' => ['order']],
    ['label' => 'Модели', 'url' => ['/admin/catalog-model/index'], 'permission' => 'catalog', 'controllers' => ['catalog-model']],
    ['label' => 'Ткани', 'url' => ['/admin/fabric-collection/index'], 'permission' => 'catalog', 'controllers' => ['fabric-collection']],
    ['label' => 'Дерево и металл', 'url' => ['/admin/surface-material/index'], 'permission' => 'catalog', 'controllers' => ['surface-material']],
    ['label' => 'Настройки', 'url' => ['/admin/settings-direction/index'], 'permission' => 'settings', 'controllers' => [
        'settings-direction',
        'settings-collection',
        'settings-category',
        'settings-color',
        'settings-badge',
    ]],
    ['label' => 'Страницы', 'url' => ['/admin/content-page/index'], 'permission' => 'pages', 'controllers' => ['content-page', 'journal-article', 'vacancy']],
    ['label' => 'Поиск', 'url' => ['/admin/search/index'], 'permission' => 'search', 'controllers' => ['search']],
    ['label' => 'Пользователи', 'url' => ['/admin/user/index'], 'permission' => 'users', 'controllers' => ['user', 'dealer-manager', 'dealer-cabinet']],
    ['label' => 'Промо и акции', 'url' => ['/admin/promo-code/index'], 'permission' => 'users', 'controllers' => ['promo-code', 'promotion-banner', 'promotion-popup', 'catalog-promotion']],
    ['label' => 'Медиатека', 'url' => ['/admin/media/index'], 'permission' => 'media', 'controllers' => ['media']],
];

/** @var AdminUser|null $user */
$user = Yii::$app->adminUser->identity;
?>
<aside class="admin-sidebar">
    <div class="admin-brand">
        <a class="admin-brand__logo" href="<?= Url::to(['/admin/dashboard/index']) ?>">
            <img src="<?= Url::to('@web/images/admin/logo.png') ?>" alt="МФ Анна">
        </a>
    </div>

    <nav class="admin-nav">
        <?php foreach ($items as $item): ?>
            <?php if ($user !== null && $user->canAccess($item['permission'])): ?>
                <?php
                $isActive = in_array($controller->id, $item['controllers'] ?? [], true);
                ?>
                <a class="admin-nav__link<?= $isActive ? ' is-active' : '' ?>" href="<?= Url::to($item['url']) ?>">
                    <?= Html::encode($item['label']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar__footer">
        <?= Html::a('Выйти', ['/admin/site/logout'], [
            'class' => 'admin-nav__link',
            'data-method' => 'post',
        ]) ?>
    </div>
</aside>
