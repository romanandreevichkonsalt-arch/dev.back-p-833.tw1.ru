<?php

use app\models\CatalogPriceCategory;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogPriceCategory[] $priceCategories */
?>
<div class="admin-card admin-card--full" id="fabric-price-categories">
    <h2 class="admin-form-section-title">Справочник категорий ткани</h2>
    <p class="admin-muted">
        Диапазоны стоимости для коллекций <strong>А+</strong> и <strong>Линия 1</strong>.
        Редактируйте здесь — при импорте из Excel в коллекцию подставляются категории из колонок F и G по номеру.
        Если в файле указан номер, которого ещё нет в списке, недостающие категории создаются автоматически.
    </p>

    <?php $form = \yii\widgets\ActiveForm::begin([
        'action' => ['save-price-categories'],
        'options' => ['class' => 'admin-form'],
    ]); ?>
    <table class="admin-table admin-table--spaced admin-table--fabric-categories">
        <thead>
        <tr>
            <th rowspan="2">№</th>
            <th colspan="3">Категория А+</th>
            <th colspan="3">Категория Линия 1</th>
        </tr>
        <tr>
            <th>Название</th>
            <th colspan="2" class="admin-fabric-categories__range-head">От / До, ₽</th>
            <th>Название</th>
            <th colspan="2" class="admin-fabric-categories__range-head">От / До, ₽</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($priceCategories === []): ?>
            <tr>
                <td colspan="7" class="admin-muted">Категории появятся после первого импорта или их можно добавить вручную в настройках.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($priceCategories as $category): ?>
            <tr>
                <td><?= (int)$category->number ?></td>
                <td>
                    <input
                        type="text"
                        class="form-control"
                        name="price_categories[<?= (int)$category->id ?>][label]"
                        value="<?= Html::encode($category->label) ?>"
                    >
                </td>
                <td class="admin-fabric-categories__range" colspan="2">
                    <div class="admin-fabric-categories__range-fields">
                        <input
                            type="number"
                            class="form-control admin-input--narrow"
                            name="price_categories[<?= (int)$category->id ?>][price_min]"
                            value="<?= $category->price_min !== null ? (int)$category->price_min : '' ?>"
                            min="0"
                            placeholder="От"
                            aria-label="От, ₽"
                        >
                        <input
                            type="number"
                            class="form-control admin-input--narrow"
                            name="price_categories[<?= (int)$category->id ?>][price_max]"
                            value="<?= $category->price_max !== null ? (int)$category->price_max : '' ?>"
                            min="0"
                            placeholder="До"
                            aria-label="До, ₽"
                        >
                    </div>
                </td>
                <td>
                    <input
                        type="text"
                        class="form-control"
                        name="price_categories[<?= (int)$category->id ?>][label_line1]"
                        value="<?= Html::encode($category->label_line1 ?? '') ?>"
                    >
                </td>
                <td class="admin-fabric-categories__range" colspan="2">
                    <div class="admin-fabric-categories__range-fields">
                        <input
                            type="number"
                            class="form-control admin-input--narrow"
                            name="price_categories[<?= (int)$category->id ?>][price_min_line1]"
                            value="<?= $category->price_min_line1 !== null ? (int)$category->price_min_line1 : '' ?>"
                            min="0"
                            placeholder="От"
                            aria-label="От, ₽ (Линия 1)"
                        >
                        <input
                            type="number"
                            class="form-control admin-input--narrow"
                            name="price_categories[<?= (int)$category->id ?>][price_max_line1]"
                            value="<?= $category->price_max_line1 !== null ? (int)$category->price_max_line1 : '' ?>"
                            min="0"
                            placeholder="До"
                            aria-label="До, ₽ (Линия 1)"
                        >
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="admin-actions">
        <?= Html::submitButton('Сохранить категории', ['class' => 'admin-btn']) ?>
    </div>
    <?php \yii\widgets\ActiveForm::end(); ?>
</div>
