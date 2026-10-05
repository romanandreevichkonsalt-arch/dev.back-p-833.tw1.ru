<?php

use app\services\journal\JournalArticleBlockBuilder;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<int, array<string, mixed>> $blocksForm */
/** @var string[]|null $allowedTypes */

$allTypeLabels = JournalArticleBlockBuilder::typeLabels();
if (!empty($allowedTypes)) {
    $typeLabels = array_intersect_key(
        $allTypeLabels,
        array_flip($allowedTypes)
    );
} else {
    $typeLabels = $allTypeLabels;
}
?>
<div class="admin-journal-blocks" data-journal-blocks>
    <div class="admin-journal-blocks__list" data-repeatable-list>
        <?php foreach ($blocksForm as $bi => $block): ?>
            <?= $this->render('_journal_block_item', ['bi' => $bi, 'block' => $block]) ?>
        <?php endforeach; ?>
    </div>

    <?php foreach ($typeLabels as $type => $label): ?>
        <template data-journal-block-template="<?= htmlspecialchars($type, ENT_QUOTES) ?>">
            <?= $this->render('_journal_block_item', [
                'bi' => '__INDEX__',
                'block' => JournalArticleBlockBuilder::emptyFormRow($type),
            ]) ?>
        </template>
    <?php endforeach; ?>

    <div class="admin-journal-blocks__fab" data-journal-block-add-anchor>
        <div class="admin-modal admin-journal-block-type-modal" data-journal-block-type-modal hidden>
            <div class="admin-modal__backdrop" data-journal-block-type-close></div>
            <div class="admin-modal__dialog admin-journal-block-type-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="journal-block-type-title">
                <div class="admin-modal__header">
                    <h3 class="admin-modal__title" id="journal-block-type-title">Выберите тип блока</h3>
                    <button type="button" class="admin-modal__close" data-journal-block-type-close aria-label="Закрыть">&times;</button>
                </div>
                <div class="admin-modal__body">
                    <div class="admin-journal-block-type-modal__grid">
                        <?php foreach ($typeLabels as $type => $label): ?>
                            <button
                                type="button"
                                class="admin-journal-block-type-modal__option"
                                data-journal-block-type-pick="<?= htmlspecialchars($type, ENT_QUOTES) ?>"
                            >
                                <?= Html::encode($label) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="admin-btn admin-journal-blocks__fab-btn" data-journal-block-add-open>
            Добавить блок
        </button>
    </div>
</div>
