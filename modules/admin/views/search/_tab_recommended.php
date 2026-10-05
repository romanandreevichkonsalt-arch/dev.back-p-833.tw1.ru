<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$searchUrl = Url::to(['/admin/search/search-products']);
?>
<?php $form = ActiveForm::begin([
    'action' => ['save-recommended'],
    'options' => ['class' => 'admin-form admin-page-editor-form'],
]); ?>

<div class="admin-page-editor admin-page-editor--full">
    <div class="admin-page-editor__main">
        <div class="admin-card">
            <p class="admin-muted admin-page-block-section__lead">
                Товары для пустого экрана поиска. На сайте показываются все заполненные слоты:
                основной блок, направления, категории и подкатегории. Пустые слоты допустимы.
            </p>

            <section class="admin-search-recommended-section">
                <h2 class="admin-search-recommended-section__title">Рекомендуем (основной)</h2>
                <p class="admin-muted">До 3 товаров из любого направления.</p>
                <div class="admin-search-recommended-slots">
                    <?php foreach ($formData['main'] as $slotIndex => $slot): ?>
                        <?= $this->render('_recommended_product_slot', [
                            'inputName' => "recommended[main][{$slotIndex}]",
                            'slot' => $slot,
                            'slotLabel' => 'Товар ' . ($slotIndex + 1),
                            'searchUrl' => $searchUrl,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-search-recommended-section">
                <h2 class="admin-search-recommended-section__title">Направления</h2>
                <p class="admin-muted">По 2 товара в каждом направлении. Можно выбирать только SKU этого направления.</p>
                <?php foreach ($formData['directions'] as $direction): ?>
                    <div class="admin-search-recommended-scope">
                        <h3 class="admin-search-recommended-scope__title"><?= Html::encode($direction['label']) ?></h3>
                        <div class="admin-search-recommended-slots">
                            <?php foreach ($direction['slots'] as $slotIndex => $slot): ?>
                                <?= $this->render('_recommended_product_slot', [
                                    'inputName' => "recommended[direction][{$direction['id']}][{$slotIndex}]",
                                    'slot' => $slot,
                                    'slotLabel' => 'Товар ' . ($slotIndex + 1),
                                    'searchUrl' => $searchUrl,
                                    'directionId' => (int)$direction['id'],
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="admin-search-recommended-section">
                <h2 class="admin-search-recommended-section__title">Категории</h2>
                <p class="admin-muted">По 2 товара на категорию.</p>
                <?php foreach ($formData['categories'] as $category): ?>
                    <div class="admin-search-recommended-scope">
                        <h3 class="admin-search-recommended-scope__title"><?= Html::encode($category['label']) ?></h3>
                        <div class="admin-search-recommended-slots">
                            <?php foreach ($category['slots'] as $slotIndex => $slot): ?>
                                <?= $this->render('_recommended_product_slot', [
                                    'inputName' => "recommended[category][{$category['id']}][{$slotIndex}]",
                                    'slot' => $slot,
                                    'slotLabel' => 'Товар ' . ($slotIndex + 1),
                                    'searchUrl' => $searchUrl,
                                    'categoryId' => (int)$category['id'],
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="admin-search-recommended-section">
                <h2 class="admin-search-recommended-section__title">Подкатегории</h2>
                <p class="admin-muted">По 2 товара на подкатегорию, сгруппировано по категориям.</p>
                <?php foreach ($formData['subcategoryGroups'] as $group): ?>
                    <div class="admin-search-recommended-group">
                        <h3 class="admin-search-recommended-group__title"><?= Html::encode($group['label']) ?></h3>
                        <?php foreach ($group['subcategories'] as $subcategory): ?>
                            <div class="admin-search-recommended-scope admin-search-recommended-scope--nested">
                                <h4 class="admin-search-recommended-scope__title"><?= Html::encode($subcategory['label']) ?></h4>
                                <div class="admin-search-recommended-slots">
                                    <?php foreach ($subcategory['slots'] as $slotIndex => $slot): ?>
                                        <?= $this->render('_recommended_product_slot', [
                                            'inputName' => "recommended[subcategory][{$subcategory['id']}][{$slotIndex}]",
                                            'slot' => $slot,
                                            'slotLabel' => 'Товар ' . ($slotIndex + 1),
                                            'searchUrl' => $searchUrl,
                                            'subcategoryId' => (int)$subcategory['id'],
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
</div>

<div class="admin-form-toolbar admin-form-toolbar--sticky">
    <?= Html::submitButton('Сохранить рекомендации', ['class' => 'admin-btn']) ?>
</div>

<?php ActiveForm::end(); ?>
