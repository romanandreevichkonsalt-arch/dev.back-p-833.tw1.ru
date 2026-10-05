<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentBlockUi;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var ContentPage[] $pages */

$this->title = 'Страницы сайта';
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-header__title"><?= Html::encode($this->title) ?></h1>
        <p class="admin-muted">Редактируйте текст и фото блоков. Изменения появляются на сайте через API.</p>
    </div>
</div>

<?php if ($pages === []): ?>
    <div class="admin-card admin-page-empty">
        <p>Страниц ещё нет. Загрузите контент командой <code>php yii seed/pages</code> на сервере.</p>
    </div>
<?php else: ?>
    <div class="admin-page-grid">
        <?php foreach ($pages as $page): ?>
            <article class="admin-page-card">
                <div class="admin-page-card__body">
                    <h2 class="admin-page-card__title"><?= Html::encode(ContentPage::titleForSlug($page->slug)) ?></h2>
                    <p class="admin-page-card__desc"><?= Html::encode(ContentBlockUi::pageDescription($page->slug)) ?></p>
                    <p class="admin-page-card__meta admin-muted">
                        Адрес API: <code>/api/v1/pages/<?= Html::encode($page->slug) ?></code>
                    </p>
                </div>
                <div class="admin-page-card__footer">
                    <?= Html::a('Редактировать', ['blocks', 'id' => $page->id], ['class' => 'admin-btn admin-btn--secondary']) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
