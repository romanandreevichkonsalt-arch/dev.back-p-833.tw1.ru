<?php

use app\models\Lead;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Lead $model */

$this->title = 'Заявка #' . $model->id;

$empty = static fn (?string $value): string => ($value !== null && trim($value) !== '')
    ? Html::encode($value)
    : '—';
?>
<div class="admin-toolbar">
    <div class="admin-actions">
        <?= Html::a('К списку', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= AdminHtml::actionIcon(['update', 'id' => $model->id], 'update') ?>
    </div>
</div>

<div class="admin-card">
    <div class="admin-detail-grid admin-detail-grid--leads">
        <div><span class="admin-detail-label">Тип</span><div><?= Html::encode($model->getTypeLabel()) ?></div></div>
        <div><span class="admin-detail-label">ФИО</span><div><?= $empty($model->name) ?></div></div>
        <div><span class="admin-detail-label">Email</span><div><?= $empty($model->email) ?></div></div>
        <div><span class="admin-detail-label">Телефон</span><div><?= $empty($model->phone) ?></div></div>
        <div><span class="admin-detail-label">Студия</span><div><?= $empty($model->studio) ?></div></div>
        <div>
            <span class="admin-detail-label">Ссылка на портфолио</span>
            <div>
                <?php if ($model->portfolio !== null && trim($model->portfolio) !== ''): ?>
                    <?= Html::a(Html::encode($model->portfolio), $model->portfolio, ['target' => '_blank', 'rel' => 'noopener noreferrer']) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
        </div>
        <div><span class="admin-detail-label">Город</span><div><?= $empty($model->city) ?></div></div>
        <div><span class="admin-detail-label">Дата создания</span><div><?= Html::encode($model->created_at) ?></div></div>
        <div>
            <span class="admin-detail-label">Статус</span>
            <div>
                <span class="admin-badge admin-badge--<?= Html::encode($model->status) ?>">
                    <?= Html::encode($model->getStatusLabel()) ?>
                </span>
            </div>
        </div>
        <div><span class="admin-detail-label">Ответственный</span><div><?= Html::encode($model->assignee?->name ?? '—') ?></div></div>
        <div>
            <span class="admin-detail-label">Файл</span>
            <div>
                <?php if ($model->hasStoredAttachment()): ?>
                    <?= Html::a(
                        Html::encode($model->getAttachmentDisplayName() ?? 'Скачать'),
                        ['download-attachment', 'id' => $model->id],
                        ['class' => 'admin-link']
                    ) ?>
                <?php else: ?>
                    <?= $empty($model->getAttachmentDisplayName()) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($model->type === Lead::TYPE_VACANCY): ?>
            <div><span class="admin-detail-label">Вакансия</span><div><?= $empty($model->vacancy_title) ?></div></div>
        <?php endif; ?>
        <?php if ($model->processed_at): ?>
            <div><span class="admin-detail-label">Обработана</span><div><?= Html::encode($model->processed_at) ?></div></div>
        <?php endif; ?>
    </div>

    <div style="margin-top:24px;">
        <span class="admin-detail-label">Комментарий</span>
        <p style="margin:8px 0 0;white-space:pre-wrap;"><?= $model->comment !== null && trim($model->comment) !== '' ? Html::encode($model->comment) : '—' ?></p>
    </div>

    <?php if ($model->manager_comment): ?>
        <div style="margin-top:24px;">
            <span class="admin-detail-label">Комментарий менеджера</span>
            <p style="margin:8px 0 0;white-space:pre-wrap;"><?= Html::encode($model->manager_comment) ?></p>
        </div>
    <?php endif; ?>
</div>
