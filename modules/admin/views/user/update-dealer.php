<?php

use app\models\DealerManager;
use app\models\DealerPriceList;
use app\models\DealerProfile;
use app\models\DealerPromoGrant;
use app\models\PromoCodeTemplate;
use app\models\User;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var User $user */
/** @var DealerProfile $profile */
/** @var app\models\DealerCashbackAccount $cashbackAccount */
/** @var DealerManager[] $managers */
/** @var DealerPriceList|null $personalPriceList */
/** @var DealerPromoGrant[] $promoGrants */
/** @var PromoCodeTemplate[] $grantableTemplates */

$this->title = 'Редактирование: ' . $user->getDisplayName();
?>
<div class="admin-toolbar">
    <?= Html::a('← Дилеры', ['index', 'tab' => 'dealers'], ['class' => 'admin-link']) ?>
    <?= Html::a('Менеджеры', ['/admin/user/index', 'tab' => 'managers'], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <div class="admin-form-grid admin-form-grid--3col">
        <?= $form->field($profile, 'company_name')->textInput() ?>
        <?= $form->field($profile, 'manager_name')->textInput() ?>
        <?= $form->field($profile, 'inn')->textInput(['maxlength' => 12]) ?>
    </div>

    <div class="admin-form-grid admin-form-grid--3col admin-form-grid--dealer-pricing">
        <?= $form->field($user, 'phone')->label('Телефон')->textInput(['placeholder' => '79998886644']) ?>
        <?= $form->field($profile, 'email')->input('email') ?>
        <?= $form->field($profile, 'personal_discount_percent')->input('number', [
            'step' => '0.01',
            'min' => 0,
            'max' => 100,
            'placeholder' => '0 — по умолчанию',
        ]) ?>
        <div class="admin-form-field">
            <label class="form-label" for="dealer-cashback-balance">Кэшбек, ₽</label>
            <input
                type="number"
                id="dealer-cashback-balance"
                class="form-control"
                name="DealerCashbackAccount[balance]"
                step="0.01"
                min="0"
                value="<?= Html::encode((string)$cashbackAccount->balance) ?>"
            >
        </div>
    </div>

    <div class="admin-form-grid admin-form-grid--3col">
        <?= $this->render('_dealer_manager_assign', [
            'form' => $form,
            'model' => $profile,
            'managers' => $managers,
        ]) ?>
    </div>

    <div class="admin-form-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?= $this->render('_dealer_manager_create_modal') ?>

<div class="admin-card">
    <h2 class="admin-card__title">Индивидуальный прайс-лист</h2>
    <?php if ($personalPriceList !== null && $personalPriceList->mediaFile !== null): ?>
        <p>Текущий файл: <strong><?= Html::encode($personalPriceList->mediaFile->filename) ?></strong></p>
    <?php else: ?>
        <p class="admin-muted">Не загружен — в ЛК дилер увидит только общий прайс.</p>
    <?php endif; ?>

    <?= Html::beginForm(['upload-personal-price-list', 'id' => $user->id], 'post', [
        'enctype' => 'multipart/form-data',
        'class' => 'admin-form',
    ]) ?>
    <div class="admin-form-grid admin-form-grid--price-list-inline">
        <div class="admin-form-field">
            <label class="form-label" for="personal-price-label">Название в ЛК</label>
            <input type="text" id="personal-price-label" name="label" class="form-control"
                   value="<?= Html::encode($personalPriceList->label ?? 'Индивидуальный прайс-лист') ?>">
        </div>
        <div class="admin-form-field">
            <label class="form-label" for="personal-price-file">Файл</label>
            <input type="file" id="personal-price-file" name="price_list_file" class="form-control"
                   accept=".pdf,.xlsx,.xls,.doc,.docx,.zip" required>
        </div>
        <div class="admin-form-grid__inline-action">
            <?= Html::submitButton('Загрузить / заменить', ['class' => 'admin-btn']) ?>
        </div>
    </div>
    <?= Html::endForm() ?>
</div>

<?= $this->render('_dealer_promos', [
    'user' => $user,
    'promoGrants' => $promoGrants,
    'grantableTemplates' => $grantableTemplates,
]) ?>

