<?php

use app\models\DealerPriceList;
use app\models\DealerProgramSettings;
use app\models\User;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\models\DealerSearch;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var DealerSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var DealerPriceList|null $globalPriceList */
/** @var DealerPriceList|null $orderFormBlank */
/** @var DealerProgramSettings $dealerProgramSettings */

$globalFileName = $globalPriceList?->mediaFile?->filename;
$globalFileTitle = $globalFileName !== null
    ? 'Текущий: ' . $globalFileName . ' (' . ($globalPriceList->updated_at ?? '') . ')'
    : 'Общий прайс не загружен';
$orderFormFileName = $orderFormBlank?->mediaFile?->filename;
$orderFormFileTitle = $orderFormFileName !== null
    ? 'Текущий: ' . $orderFormFileName . ' (' . ($orderFormBlank->updated_at ?? '') . ')'
    : 'Бланк заказа не загружен';
$cashbackHint = 'По умолчанию для новых начислений (если у дилера не задан свой срок).';
$documentAccept = '.pdf,.xlsx,.xls,.doc,.docx,.zip';
?>
<div class="admin-card admin-dealers-toolbar">
    <div class="admin-dealers-toolbar__row">
        <div class="admin-form-field admin-dealers-toolbar__field admin-dealers-toolbar__field--price">
            <label class="form-label">Общий прайс</label>
            <div class="admin-dealers-toolbar__control">
                <?php if ($globalFileName !== null): ?>
                    <span class="admin-dealers-toolbar__file" title="<?= Html::encode($globalFileTitle) ?>">
                        <?= Html::encode($globalFileName) ?>
                    </span>
                <?php else: ?>
                    <span class="admin-muted admin-dealers-toolbar__file-placeholder">Не загружен</span>
                <?php endif; ?>
                <?= Html::beginForm(['upload-global-price-list'], 'post', [
                    'enctype' => 'multipart/form-data',
                    'class' => 'admin-dealers-toolbar__icon-form',
                    'id' => 'global-price-upload-form',
                ]) ?>
                <?= Html::hiddenInput('label', $globalPriceList->label ?? 'Общий прайс-лист') ?>
                <?= Html::fileInput('price_list_file', null, [
                    'id' => 'global-price-upload-input',
                    'class' => 'admin-global-price-upload__input',
                    'accept' => $documentAccept,
                ]) ?>
                <?= Html::label(AdminHtml::icon('upload'), 'global-price-upload-input', [
                    'class' => 'admin-icon-btn admin-dealers-toolbar__icon-btn',
                    'title' => $globalFileTitle . '. PDF, Excel, Word, ZIP.',
                    'aria-label' => 'Загрузить общий прайс-лист',
                ]) ?>
                <?= Html::endForm() ?>
            </div>
        </div>

        <div class="admin-form-field admin-dealers-toolbar__field admin-dealers-toolbar__field--order-form">
            <label class="form-label">Бланк заказа</label>
            <div class="admin-dealers-toolbar__control">
                <?php if ($orderFormFileName !== null): ?>
                    <span class="admin-dealers-toolbar__file" title="<?= Html::encode($orderFormFileTitle) ?>">
                        <?= Html::encode($orderFormFileName) ?>
                    </span>
                    <?= Html::a(AdminHtml::icon('download'), ['download-order-form-blank'], [
                        'class' => 'admin-icon-btn admin-dealers-toolbar__icon-btn',
                        'title' => 'Скачать бланк заказа',
                        'aria-label' => 'Скачать бланк заказа',
                    ]) ?>
                <?php else: ?>
                    <span class="admin-muted admin-dealers-toolbar__file-placeholder">Не загружен</span>
                <?php endif; ?>
                <?= Html::beginForm(['upload-order-form-blank'], 'post', [
                    'enctype' => 'multipart/form-data',
                    'class' => 'admin-dealers-toolbar__icon-form',
                    'id' => 'order-form-upload-form',
                ]) ?>
                <?= Html::hiddenInput('label', $orderFormBlank->label ?? 'Бланк заказа') ?>
                <?= Html::fileInput('order_form_file', null, [
                    'id' => 'order-form-upload-input',
                    'class' => 'admin-global-price-upload__input',
                    'accept' => $documentAccept,
                ]) ?>
                <?= Html::label(AdminHtml::icon('upload'), 'order-form-upload-input', [
                    'class' => 'admin-icon-btn admin-dealers-toolbar__icon-btn',
                    'title' => $orderFormFileTitle . '. PDF, Excel, Word, ZIP.',
                    'aria-label' => 'Загрузить бланк заказа',
                ]) ?>
                <?= Html::endForm() ?>
            </div>
        </div>

        <?= Html::beginForm(['save-dealer-program-settings'], 'post', [
            'class' => 'admin-dealers-toolbar__field admin-dealers-toolbar__field--cashback',
            'id' => 'dealer-cashback-settings-form',
        ]) ?>
        <div class="admin-form-field">
            <label class="form-label" for="cashback-default-expiry-days">Срок кэшбека, дней</label>
            <div class="admin-dealers-toolbar__control">
                <input
                    type="number"
                    id="cashback-default-expiry-days"
                    class="form-control admin-dealers-toolbar__days-input"
                    name="cashback_default_expiry_days"
                    min="1"
                    max="3650"
                    value="<?= (int)$dealerProgramSettings->cashback_default_expiry_days ?>"
                    title="<?= Html::encode($cashbackHint) ?>"
                    required
                >
                <?= Html::submitButton(AdminHtml::icon('save'), [
                    'class' => 'admin-icon-btn admin-dealers-toolbar__icon-btn',
                    'title' => 'Сохранить срок кэшбека',
                    'aria-label' => 'Сохранить срок кэшбека',
                ]) ?>
            </div>
        </div>
        <?= Html::endForm() ?>
    </div>
</div>

<div class="admin-card admin-dealers-filter-card">
    <div class="admin-dealers-filter-bar">
        <?php $form = ActiveForm::begin([
            'method' => 'get',
            'action' => ['index', 'tab' => 'dealers'],
            'options' => ['class' => 'admin-form admin-filter-form admin-dealers-filter-bar__search'],
        ]); ?>
        <?php if ($searchModel->archive): ?>
            <?= Html::hiddenInput('archive', '1') ?>
        <?php endif; ?>
        <div class="admin-filter-grid admin-filter-grid--dealers-search">
            <?= $form->field($searchModel, 'q')->textInput(['placeholder' => 'ИНН, название, логин, email']) ?>
            <div class="admin-filter-actions">
                <?= Html::submitButton('Найти', ['class' => 'admin-btn']) ?>
                <?= Html::a(
                    'Сбросить',
                    $searchModel->archive
                        ? ['index', 'tab' => 'dealers', 'archive' => 1]
                        : ['index', 'tab' => 'dealers'],
                    ['class' => 'admin-btn admin-btn--secondary'],
                ) ?>
            </div>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
$dealerListQuery = ['tab' => 'dealers'];
if ($searchModel->q !== null && $searchModel->q !== '') {
    $dealerListQuery['DealerSearch']['q'] = $searchModel->q;
}
$archiveToggleUrl = $searchModel->archive
    ? Url::to(array_merge(['index'], $dealerListQuery))
    : Url::to(array_merge(['index', 'archive' => 1], $dealerListQuery));
$archiveToggleLabel = $searchModel->archive ? '← К активным' : 'Архив';
$gridToolbarActions = Html::a($archiveToggleLabel, $archiveToggleUrl, [
    'class' => 'admin-btn admin-btn--secondary admin-dealers-grid-toolbar__btn',
]);
?>
<div class="admin-card admin-dealers-grid-card">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'admin-table'],
        'summary' => 'Показано {begin}–{end} из {totalCount}',
        'layout' => '<div class="admin-dealers-grid-toolbar"><div class="summary">{summary}</div>'
            . '<div class="admin-dealers-grid-toolbar__actions">' . $gridToolbarActions . '</div></div>{items}{pager}',
        'columns' => [
            'id',
            [
                'label' => 'Компания / ФИО',
                'value' => static fn (User $model): string => (string)($model->dealerProfile?->company_name ?? '—'),
            ],
            [
                'label' => 'ИНН',
                'value' => static fn (User $model): string => (string)($model->dealerProfile?->inn ?? '—'),
            ],
            'username',
            [
                'label' => 'Тип',
                'value' => static fn (User $model): string => $model->dealerProfile?->getTypeLabel() ?? '—',
            ],
            [
                'label' => 'Статус',
                'format' => 'raw',
                'value' => static fn (User $model): string => $model->is_blocked
                    ? '<span class="admin-badge admin-badge--danger">Заблокирован</span>'
                    : '<span class="admin-badge admin-badge--success">Активен</span>',
            ],
            [
                'label' => 'Email',
                'value' => static fn (User $model): ?string => $model->dealerProfile?->email,
            ],
            'created_at',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update} {block} {copy} {send}',
                'contentOptions' => ['class' => 'admin-table-actions'],
                'headerOptions' => ['class' => 'admin-table-actions'],
                'buttons' => AdminHtml::dealerGridActionButtons(),
            ],
        ],
    ]) ?>
</div>
<?php
$this->registerJs(<<<'JS'
(function () {
    var input = document.getElementById('global-price-upload-input');
    var form = document.getElementById('global-price-upload-form');
    if (input && form) {
        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) {
                form.submit();
            }
        });
    }

    var orderInput = document.getElementById('order-form-upload-input');
    var orderForm = document.getElementById('order-form-upload-form');
    if (orderInput && orderForm) {
        orderInput.addEventListener('change', function () {
            if (orderInput.files && orderInput.files.length > 0) {
                orderForm.submit();
            }
        });
    }

    var cashbackForm = document.getElementById('dealer-cashback-settings-form');
    var daysInput = document.getElementById('cashback-default-expiry-days');
    if (cashbackForm && daysInput) {
        var initialDays = daysInput.value;
        cashbackForm.addEventListener('submit', function (e) {
            if (daysInput.value === initialDays) {
                e.preventDefault();
                return;
            }
            var message =
                'Сохранить срок кэшбека по умолчанию: ' +
                daysInput.value +
                ' дн.?\n\n' +
                'Значение применится к новым начислениям (если у дилера не задан свой срок).';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    }
})();
JS);
?>
