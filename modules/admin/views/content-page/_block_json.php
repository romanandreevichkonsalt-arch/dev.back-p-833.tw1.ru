<?php

use app\models\ContentBlock;
use app\modules\admin\helpers\ContentBlockUi;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var ContentBlock $block */
?>
<div class="admin-page-block-section admin-page-block-section--json">
    <h3 class="admin-page-block-section__title">Содержимое блока «<?= Html::encode(ContentBlockUi::keyLabel($block->block_key)) ?>»</h3>
    <div class="admin-page-json-help">
        <p>Этот блок содержит несколько элементов (карточки, списки, слайды). Редактирование в формате данных.</p>
        <ul>
            <li>Для <strong>фото</strong>: откройте медиатеку, скопируйте ссылку и вставьте в поле <code>"src"</code> внутри <code>"image"</code>.</li>
            <li>Для <strong>текста</strong>: измените значения в кавычках, не удаляя запятые и скобки.</li>
            <li>После правок нажмите «Сохранить». При ошибке форматирования блок не сохранится.</li>
        </ul>
        <p class="admin-muted"><?= Html::encode(ContentBlockUi::keyHint($block->block_key)) ?></p>
    </div>
    <div class="admin-page-field">
        <label class="form-label" for="json_data">Данные блока</label>
        <?= Html::textarea('json_data', $formData['json_data'] ?? '{}', [
            'class' => 'form-control admin-page-json-editor',
            'id' => 'json_data',
            'rows' => 22,
        ]) ?>
    </div>
</div>
