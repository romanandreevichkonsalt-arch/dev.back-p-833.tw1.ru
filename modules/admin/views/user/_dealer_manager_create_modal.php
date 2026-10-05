<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
?>
<div class="admin-modal" id="dealer-manager-create-modal" hidden>
    <div class="admin-modal__backdrop" data-dealer-manager-close></div>
    <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dealer-manager-modal-title">
        <div class="admin-modal__header">
            <h3 class="admin-modal__title" id="dealer-manager-modal-title">Новый менеджер</h3>
            <button type="button" class="admin-modal__close" data-dealer-manager-close aria-label="Закрыть">&times;</button>
        </div>
        <div class="admin-modal__body">
            <div class="admin-form-grid admin-form-grid--2col">
                <div class="admin-form-field">
                    <label class="form-label" for="new-manager-name">ФИО</label>
                    <input type="text" id="new-manager-name" class="form-control">
                </div>
                <div class="admin-form-field">
                    <label class="form-label" for="new-manager-phone">Телефон</label>
                    <input type="text" id="new-manager-phone" class="form-control">
                </div>
                <div class="admin-form-field">
                    <label class="form-label" for="new-manager-email">Email</label>
                    <input type="email" id="new-manager-email" class="form-control">
                </div>
                <div class="admin-form-field admin-form-field--full">
                    <?= $this->render('@app/modules/admin/views/dealer-manager/_work_hours_fields', [
                        'workHours' => null,
                        'fieldPrefix' => 'WorkHours',
                        'idPrefix' => 'new-manager-hours',
                    ]) ?>
                </div>
                <div class="admin-form-field">
                    <label class="form-label" for="new-manager-role">Должность</label>
                    <input type="text" id="new-manager-role" class="form-control" placeholder="Менеджер заказов">
                </div>
            </div>
            <p class="admin-form-error" id="new-manager-error" hidden></p>
        </div>
        <div class="admin-modal__footer">
            <?= Html::button('Отмена', ['class' => 'admin-btn admin-btn--secondary', 'data-dealer-manager-close' => true]) ?>
            <?= Html::button('Создать и выбрать', ['class' => 'admin-btn', 'id' => 'dealer-manager-create-submit']) ?>
        </div>
    </div>
</div>
