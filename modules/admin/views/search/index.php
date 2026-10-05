<?php

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $tab */
/** @var app\models\SearchFrequentQuery[] $queries */
/** @var app\models\SearchCategory[] $categories */
/** @var array<string, mixed> $recommendedFormData */
/** @var array<string, mixed> $catalogPriorityFormData */

$this->title = 'Поиск';

$tabs = [
    'queries' => [
        'label' => 'Частые запросы и категории',
        'url' => ['index', 'tab' => 'queries'],
    ],
    'recommended' => [
        'label' => 'Рекомендуемые товары',
        'url' => ['index', 'tab' => 'recommended'],
    ],
    'catalog' => [
        'label' => 'Каталог',
        'url' => ['index', 'tab' => 'catalog'],
    ],
];
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-header__title"><?= Html::encode($this->title) ?></h1>
        <p class="admin-muted">Пустое состояние строки поиска на сайте</p>
    </div>
</div>

<?= AdminHtml::pageTabs($tabs, $tab, 'Разделы поиска') ?>

<?php if ($tab === 'recommended'): ?>
    <?= $this->render('_tab_recommended', ['formData' => $recommendedFormData]) ?>
<?php elseif ($tab === 'catalog'): ?>
    <?= $this->render('_tab_catalog', ['formData' => $catalogPriorityFormData]) ?>
<?php else: ?>
    <?= $this->render('_tab_queries', ['queries' => $queries, 'categories' => $categories]) ?>
<?php endif; ?>
