<?php

/** @var yii\web\View $this */
/** @var app\models\Category[] $roots */

use yii\bootstrap5\Html;

$this->title = 'Дерево категорий';

function renderTree(array $nodes, int $level = 0): string
{
    $html = '<ul class="list-unstyled ms-' . ($level > 0 ? '3' : '0') . '">';
    foreach ($nodes as $node) {
        $html .= '<li class="mb-1">';
        $html .= Html::a(Html::encode($node->name), ['/admin/category/view', 'id' => $node->id]);
        $html .= ' <span class="text-muted small">(' . Html::encode($node->slug) . ')</span>';
        if ($node->children) {
            $html .= renderTree($node->children, $level + 1);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= renderTree($roots) ?>
