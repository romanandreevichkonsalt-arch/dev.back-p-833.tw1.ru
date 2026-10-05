<?php

use app\modules\admin\controllers\UserController;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $activeTab */
/** @var yii\data\ActiveDataProvider|null $dataProvider */
/** @var app\modules\admin\models\DealerSearch|app\modules\admin\models\UserSearch|null $searchModel */
/** @var app\models\DealerPriceList|null $globalPriceList */
/** @var app\models\DealerPriceList|null $orderFormBlank */
/** @var app\models\DealerProgramSettings|null $dealerProgramSettings */
/** @var app\models\DealerManager[]|null $managers */

$this->title = 'Пользователи';
?>
<div class="admin-toolbar">
    <?php if ($activeTab === UserController::TAB_DEALERS): ?>
        <?= Html::a('Добавить дилера', ['create'], ['class' => 'admin-btn']) ?>
    <?php elseif ($activeTab === UserController::TAB_MANAGERS): ?>
        <?= Html::a('Добавить менеджера', ['/admin/dealer-manager/create'], ['class' => 'admin-btn']) ?>
    <?php endif; ?>
</div>

<?= AdminHtml::pageTabs([
    UserController::TAB_DEALERS => [
        'label' => 'Дилеры',
        'url' => ['index', 'tab' => UserController::TAB_DEALERS],
    ],
    UserController::TAB_CUSTOMERS => [
        'label' => 'Пользователи',
        'url' => ['index', 'tab' => UserController::TAB_CUSTOMERS],
    ],
    UserController::TAB_MANAGERS => [
        'label' => 'Менеджеры',
        'url' => ['index', 'tab' => UserController::TAB_MANAGERS],
    ],
], $activeTab, 'Разделы пользователей') ?>

<?php if ($activeTab === UserController::TAB_DEALERS): ?>
    <?= $this->render('_tab_dealers', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider,
        'globalPriceList' => $globalPriceList ?? null,
        'orderFormBlank' => $orderFormBlank ?? null,
        'dealerProgramSettings' => $dealerProgramSettings ?? \app\models\DealerProgramSettings::getSingleton(),
    ]) ?>
<?php elseif ($activeTab === UserController::TAB_MANAGERS): ?>
    <?= $this->render('_tab_managers', [
        'managers' => $managers ?? [],
    ]) ?>
<?php else: ?>
    <?= $this->render('_tab_customers', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider,
    ]) ?>
<?php endif; ?>
