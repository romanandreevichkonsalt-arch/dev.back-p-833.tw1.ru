<?php

use app\models\VacancyDirection;
use app\modules\admin\helpers\AdminHtml;
use app\services\vacancy\VacancyService;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$directions = VacancyDirection::find()
    ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
    ->all();
$vacanciesByDirectionId = (new VacancyService())->vacanciesByDirectionForAdmin();
?>
<?php if ($directions === []): ?>
    <div class="admin-page-block-section">
        <p class="admin-muted">Сначала добавьте направления на вкладке «Направления».</p>
    </div>
<?php else: ?>
    <div class="admin-faq-tabs">
        <div class="admin-faq-tabs__nav" role="tablist">
            <?php foreach ($directions as $di => $direction): ?>
                <?php
                $directionVacancies = $vacanciesByDirectionId[(int)$direction->id] ?? [];
                $vacancyCount = count($directionVacancies);
                ?>
                <button
                    type="button"
                    class="admin-faq-tabs__tab<?= $di === 0 ? ' is-active' : '' ?>"
                    role="tab"
                    data-faq-tab="<?= (int)$di ?>"
                    aria-selected="<?= $di === 0 ? 'true' : 'false' ?>"
                >
                    <span class="admin-faq-tabs__tab-num"><?= Html::encode($direction->number !== '' ? $direction->number : sprintf('%02d', $di + 1)) ?></span>
                    <span class="admin-faq-tabs__tab-label"><?= Html::encode($direction->title) ?></span>
                    <span class="admin-muted">(<?= (int)$vacancyCount ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($directions as $di => $direction): ?>
            <?php $directionVacancies = $vacanciesByDirectionId[(int)$direction->id] ?? []; ?>
            <div class="admin-faq-tabs__panel<?= $di === 0 ? ' is-active' : '' ?>" data-faq-panel="<?= (int)$di ?>">
                <div class="admin-faq-tabs__panel-head">
                    <h4 class="admin-faq-tabs__panel-title"><?= Html::encode($direction->title) ?></h4>
                    <p class="admin-muted admin-faq-tabs__panel-meta">
                        Slug: <code><?= Html::encode($direction->slug) ?></code>
                        · вакансий: <?= count($directionVacancies) ?>
                    </p>
                </div>

                <div class="admin-journal-tabs__articles">
                    <div class="admin-content-repeatable__toolbar">
                        <h4 class="admin-content-nested__title">Вакансии направления</h4>
                        <?= Html::a(
                            'Добавить вакансию',
                            Url::to(['/admin/vacancy/create', 'direction_id' => (int)$direction->id]),
                            ['class' => 'admin-btn admin-btn--ghost admin-btn--small']
                        ) ?>
                    </div>

                    <?php if ($directionVacancies === []): ?>
                        <p class="admin-muted admin-journal-tabs__empty">В этом направлении пока нет вакансий.</p>
                    <?php else: ?>
                        <ul class="admin-journal-tabs__article-list">
                            <?php foreach ($directionVacancies as $vacancy): ?>
                                <li class="admin-journal-tabs__article-item">
                                    <div class="admin-journal-tabs__article-main">
                                        <span class="admin-journal-tabs__article-title"><?= Html::encode($vacancy->title) ?></span>
                                        <?php if (trim((string)$vacancy->department) !== ''): ?>
                                            <span class="admin-muted">· <?= Html::encode($vacancy->department) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?= AdminHtml::actionIcon(['/admin/vacancy/update', 'id' => $vacancy->id], 'update') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
