<?php

use app\models\AdminUser;
use app\models\Lead;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Lead $model */
/** @var AdminUser[] $managers */

$this->title = 'Заявка #' . $model->id;
?>
<div class="admin-card" style="max-width:640px;">
    <?php $form = ActiveForm::begin([
        'options' => [
            'class' => 'admin-form',
            'enctype' => 'multipart/form-data',
        ],
    ]); ?>

    <?= $form->field($model, 'status')->dropDownList(Lead::statusLabels()) ?>
    <?= $form->field($model, 'amount')->input('number', ['step' => '0.01', 'min' => 0]) ?>
    <?= $form->field($model, 'assigned_to')->dropDownList(
        ['' => 'Не назначен'] + ArrayHelper::map($managers, 'id', 'name')
    ) ?>
    <?= $form->field($model, 'manager_comment')->textarea(['rows' => 5]) ?>

    <div class="form-group">
        <label class="form-label" for="lead-attachment">Файл</label>
        <?php if ($model->hasStoredAttachment() || $model->getAttachmentDisplayName() !== null): ?>
            <p class="admin-muted" style="margin:0 0 8px;">
                Текущий:
                <?php if ($model->hasStoredAttachment()): ?>
                    <?= Html::a(
                        Html::encode($model->getAttachmentDisplayName() ?? 'Скачать'),
                        ['download-attachment', 'id' => $model->id],
                        ['class' => 'admin-link']
                    ) ?>
                <?php else: ?>
                    <?= Html::encode($model->getAttachmentDisplayName()) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <input type="file" id="lead-attachment" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx">
        <p class="admin-muted" style="margin:6px 0 0;font-size:13px;">PDF, Word (doc, docx), Excel (xls, xlsx), до 20 МБ. Загрузка заменит текущий файл.</p>
    </div>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['view', 'id' => $model->id], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
