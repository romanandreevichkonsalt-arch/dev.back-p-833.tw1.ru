<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $companyName */
/** @var string $username */
/** @var string $password */
/** @var string $cabinetUrl */

$this->title = 'Доступ в личный кабинет дилера';
?>
<p>Здравствуйте<?= $companyName !== '' ? ', ' . Html::encode($companyName) : '' ?>!</p>

<p>Для вас создан доступ в личный кабинет дилера мебельной фабрики «Анна».</p>

<p><strong>Логин:</strong> <?= Html::encode($username) ?><br>
<strong>Пароль:</strong> <?= Html::encode($password) ?></p>

<?php if ($cabinetUrl !== ''): ?>
    <p><a href="<?= Html::encode($cabinetUrl) ?>">Перейти в личный кабинет</a></p>
<?php endif; ?>

<p>При первом входе необходимо заполнить профиль: ИНН, мобильный телефон, email и ФИО менеджера.</p>

<p>Если вы не запрашивали доступ, проигнорируйте это письмо.</p>
