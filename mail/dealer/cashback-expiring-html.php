<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $companyName */
/** @var string $amount */
/** @var string $expiresAt */
/** @var string $cabinetUrl */

$this->title = 'Кэшбек скоро сгорит';
?>
<p>Здравствуйте<?= $companyName !== '' ? ', ' . Html::encode($companyName) : '' ?>!</p>

<p>Напоминаем: <?= Html::encode($amount) ?> ₽ кэшбека сгорят <?= Html::encode($expiresAt) ?>.</p>

<p>Используйте кэшбек при оформлении следующего заказа в личном кабинете дилера.</p>

<?php if ($cabinetUrl !== ''): ?>
    <p><a href="<?= Html::encode($cabinetUrl) ?>">Перейти в личный кабинет</a></p>
<?php endif; ?>

<p>Если вы уже использовали кэшбек, проигнорируйте это письмо.</p>
