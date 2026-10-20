<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var array<string, string> $variableHints */

use yii\helpers\Html;

$levels = [
    'direction' => 'Направление (корень каталога в линейке)',
    'category' => 'Категория в направлении',
    'subcategory' => 'Подкатегория',
];
?>
<div class="admin-page-block-section admin-catalog-seo-vars">
    <h3 class="admin-page-block-section__title">Переменные</h3>
    <p class="admin-muted">Подставляются автоматически при открытии раздела на сайте.</p>
    <ul class="admin-catalog-seo-vars__list">
        <?php foreach ($variableHints as $token => $hint): ?>
            <li><code><?= Html::encode($token) ?></code> — <?= Html::encode($hint) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php foreach ($levels as $levelKey => $levelTitle): ?>
    <div class="admin-page-block-section">
        <h3 class="admin-page-block-section__title"><?= Html::encode($levelTitle) ?></h3>
        <div class="admin-content-row__grid admin-content-row__grid--seo">
            <?= $this->render('_block_field', [
                'name' => "seo_{$levelKey}_title",
                'label' => 'Title (вкладка и поиск)',
                'value' => $formData["seo_{$levelKey}_title"] ?? '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => "seo_{$levelKey}_h1",
                'label' => 'H1 на странице',
                'value' => $formData["seo_{$levelKey}_h1"] ?? '',
            ]) ?>
        </div>
        <?= $this->render('_block_field', [
            'name' => "seo_{$levelKey}_description",
            'label' => 'Meta description',
            'type' => 'textarea',
            'rows' => 3,
            'value' => $formData["seo_{$levelKey}_description"] ?? '',
            'hint' => 'До ~160 символов после подстановки переменных.',
        ]) ?>
    </div>
<?php endforeach; ?>
