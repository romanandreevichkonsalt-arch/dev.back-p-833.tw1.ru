<?php

use app\models\ContentPage;
use app\modules\admin\helpers\HomePageJournalHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */

$journalPageId = ContentPage::find()->select('id')->where(['slug' => 'journal'])->scalar();
$journalLink = $journalPageId
    ? Html::a('страницы «Журнал»', ['/admin/content-page/blocks', 'id' => $journalPageId], ['class' => 'admin-link'])
    : 'страницы «Журнал»';
?>
<div class="admin-page-block-section">
    <p class="admin-muted admin-page-block-section__lead">
        На главной автоматически выводятся <?= HomePageJournalHelper::ARTICLE_COUNT ?>
        последние статьи из <?= $journalLink ?>.
        Отдельная настройка не нужна.
    </p>
</div>
