<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$regions = $formData['regions'] ?? [];
if ($regions === []) {
    $regions = [['id' => '', 'name' => '', 'points_count' => '', 'default_open' => false, 'stores' => []]];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Регионы</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить регион</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">Координаты: долгота и широта (как на карте Яндекса).</p>
    <div data-repeatable-list>
        <?php foreach ($regions as $ri => $region): ?>
            <?php
            $stores = $region['stores'] ?? [];
            if ($stores === []) {
                $stores = [['id' => '', 'name' => '', 'address' => '', 'lon' => '', 'lat' => '', 'phone' => '', 'hours' => '']];
            }
            ?>
            <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Регион ' . ($ri + 1)]) ?>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "regions[{$ri}][id]",
                        'label' => 'ID региона',
                        'value' => $region['id'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "regions[{$ri}][name]",
                        'label' => 'Название',
                        'value' => $region['name'] ?? '',
                    ]) ?>
                </div>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "regions[{$ri}][points_count]",
                        'label' => 'Число точек',
                        'value' => $region['points_count'] ?? '',
                        'hint' => 'Можно оставить пустым — посчитается из салонов.',
                    ]) ?>
                    <div class="admin-page-field">
                        <label class="form-label">
                            <?= Html::checkbox("regions[{$ri}][default_open]", !empty($region['default_open']), ['value' => '1']) ?>
                            Открыт по умолчанию
                        </label>
                    </div>
                </div>

                <div class="admin-content-nested" data-repeatable data-parent-index="<?= (int)$ri ?>">
                    <div class="admin-content-repeatable__toolbar">
                        <h4 class="admin-content-nested__title">Салоны</h4>
                        <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить салон</button>
                    </div>
                    <div data-repeatable-list>
                        <?php foreach ($stores as $si => $store): ?>
                            <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
                                <?= $this->render('_block_row_header', ['title' => 'Салон ' . ($si + 1)]) ?>
                                <div class="admin-content-row__grid">
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][id]",
                                        'label' => 'ID салона',
                                        'value' => $store['id'] ?? '',
                                    ]) ?>
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][name]",
                                        'label' => 'Название',
                                        'value' => $store['name'] ?? '',
                                    ]) ?>
                                </div>
                                <?= $this->render('_block_field', [
                                    'name' => "regions[{$ri}][stores][{$si}][address]",
                                    'label' => 'Адрес',
                                    'value' => $store['address'] ?? '',
                                ]) ?>
                                <div class="admin-content-row__grid">
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][lon]",
                                        'label' => 'Долгота',
                                        'value' => $store['lon'] ?? '',
                                    ]) ?>
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][lat]",
                                        'label' => 'Широта',
                                        'value' => $store['lat'] ?? '',
                                    ]) ?>
                                </div>
                                <div class="admin-content-row__grid">
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][phone]",
                                        'label' => 'Телефон',
                                        'value' => $store['phone'] ?? '',
                                    ]) ?>
                                    <?= $this->render('_block_field', [
                                        'name' => "regions[{$ri}][stores][{$si}][hours]",
                                        'label' => 'Часы работы',
                                        'value' => $store['hours'] ?? '',
                                    ]) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeatable-template>
                        <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
                            <?= $this->render('_block_row_header', ['title' => 'Новый салон']) ?>
                            <div class="admin-content-row__grid">
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][id]', 'label' => 'ID салона', 'value' => '']) ?>
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][name]', 'label' => 'Название', 'value' => '']) ?>
                            </div>
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][address]', 'label' => 'Адрес', 'value' => '']) ?>
                            <div class="admin-content-row__grid">
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][lon]', 'label' => 'Долгота', 'value' => '']) ?>
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][lat]', 'label' => 'Широта', 'value' => '']) ?>
                            </div>
                            <div class="admin-content-row__grid">
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][phone]', 'label' => 'Телефон', 'value' => '']) ?>
                                <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][hours]', 'label' => 'Часы работы', 'value' => '']) ?>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый регион']) ?>
            <div class="admin-content-row__grid">
                <?= $this->render('_block_field', ['name' => 'regions[__INDEX__][id]', 'label' => 'ID региона', 'value' => '']) ?>
                <?= $this->render('_block_field', ['name' => 'regions[__INDEX__][name]', 'label' => 'Название', 'value' => '']) ?>
            </div>
            <div class="admin-content-row__grid">
                <?= $this->render('_block_field', ['name' => 'regions[__INDEX__][points_count]', 'label' => 'Число точек', 'value' => '']) ?>
                <div class="admin-page-field">
                    <label class="form-label"><input type="checkbox" name="regions[__INDEX__][default_open]" value="1"> Открыт по умолчанию</label>
                </div>
            </div>
            <div class="admin-content-nested" data-repeatable data-parent-index="__INDEX__">
                <div class="admin-content-repeatable__toolbar">
                    <h4 class="admin-content-nested__title">Салоны</h4>
                    <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить салон</button>
                </div>
                <div data-repeatable-list></div>
                <template data-repeatable-template>
                    <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
                        <?= $this->render('_block_row_header', ['title' => 'Новый салон']) ?>
                        <div class="admin-content-row__grid">
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][id]', 'label' => 'ID салона', 'value' => '']) ?>
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][name]', 'label' => 'Название', 'value' => '']) ?>
                        </div>
                        <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][address]', 'label' => 'Адрес', 'value' => '']) ?>
                        <div class="admin-content-row__grid">
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][lon]', 'label' => 'Долгота', 'value' => '']) ?>
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][lat]', 'label' => 'Широта', 'value' => '']) ?>
                        </div>
                        <div class="admin-content-row__grid">
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][phone]', 'label' => 'Телефон', 'value' => '']) ?>
                            <?= $this->render('_block_field', ['name' => 'regions[__PARENT_INDEX__][stores][__INDEX__][hours]', 'label' => 'Часы работы', 'value' => '']) ?>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
