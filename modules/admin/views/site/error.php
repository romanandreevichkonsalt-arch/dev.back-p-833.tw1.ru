<?php

use yii\helpers\Html;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Throwable $exception */
/** @var int $statusCode */
/** @var string $userMessage */
/** @var string $developerReport */

$this->title = $statusCode >= 500 ? 'Ошибка сервера' : $name;

$reportJson = Json::encode($developerReport);
$this->registerJs(<<<JS
(function () {
    var report = {$reportJson};
    var copyBtn = document.getElementById('admin-error-copy');
    var backBtn = document.getElementById('admin-error-back');
    var hint = document.getElementById('admin-error-copy-hint');

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/admin/dashboard/index';
            }
        });
    }

    if (!copyBtn) {
        return;
    }

    copyBtn.addEventListener('click', function () {
        var done = function () {
            if (hint) {
                hint.hidden = false;
                window.setTimeout(function () { hint.hidden = true; }, 2500);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(report).then(done).catch(fallback);
            return;
        }

        fallback();

        function fallback() {
            var area = document.createElement('textarea');
            area.value = report;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                done();
            } finally {
                document.body.removeChild(area);
            }
        }
    });
})();
JS);
?>
<div class="admin-card admin-error-page">
    <div class="admin-error-page__badge"><?= (int)$statusCode ?></div>
    <h1 class="admin-error-page__title"><?= Html::encode($this->title) ?></h1>
    <div class="admin-flash admin-flash--error admin-error-page__message">
        <?= nl2br(Html::encode($userMessage)) ?>
    </div>
    <p class="admin-muted admin-error-page__hint">
        Если ошибка повторяется, нажмите «Скопировать ошибку для разработчика» и передайте текст в поддержку.
    </p>
    <div class="admin-form-actions admin-error-page__actions">
        <button type="button" class="admin-btn admin-btn--secondary" id="admin-error-back">Вернуться</button>
        <button type="button" class="admin-btn" id="admin-error-copy">Скопировать ошибку для разработчика</button>
        <span class="admin-muted admin-error-page__copied" id="admin-error-copy-hint" hidden>Скопировано</span>
    </div>
</div>
