<?php

namespace app\modules\admin\helpers;

use app\models\CatalogFabricColor;
use yii\base\Model;
use yii\helpers\Html;
use yii\widgets\ActiveField;
use yii\widgets\ActiveForm;

class AdminHtml
{
    public static function icon(string $name): string
    {
        $icons = [
            'view' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>',
            'edit' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
            'delete' => '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
            'add' => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
            'search' => '<circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.35-4.35"></path>',
            'clear' => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
            'block' => '<circle cx="12" cy="12" r="10"></circle><path d="m4.9 4.9 14.2 14.2"></path>',
            'unblock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path>',
            'send' => '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>',
            'copy' => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
            'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline>',
            'list-check' => '<path d="M11 18H3"></path><path d="m15 18 2 2 4-4"></path><path d="M16 12H3"></path><path d="M16 6H3"></path>',
            'sparkles' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path>',
        ];

        $body = $icons[$name] ?? $icons['view'];

        return '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
    }

    public static function repeatableRemoveButton(array $options = []): string
    {
        return Html::button(self::icon('delete'), array_merge([
            'type' => 'button',
            'class' => 'admin-icon-btn admin-icon-btn--danger',
            'data-repeatable-remove' => true,
            'title' => 'Удалить',
            'aria-label' => 'Удалить',
        ], $options));
    }

    public static function repeatableAddButton(string $title = 'Добавить пункт', array $options = []): string
    {
        return Html::button(self::icon('add'), array_merge([
            'type' => 'button',
            'class' => 'admin-icon-btn',
            'data-repeatable-add' => true,
            'title' => $title,
            'aria-label' => $title,
        ], $options));
    }

    public static function sectionIcon(string $name, string $title): string
    {
        return Html::tag('span', self::icon($name), [
            'class' => 'admin-section-icon',
            'title' => $title,
            'aria-label' => $title,
            'role' => 'img',
        ]);
    }

    public static function actionIcon(string|array $url, string $type, array $options = []): string
    {
        $titles = [
            'view' => 'Открыть',
            'edit' => 'Изменить',
            'delete' => 'Удалить',
            'add' => 'Добавить подкатегорию',
            'block' => 'Заблокировать',
            'unblock' => 'Разблокировать',
            'send' => 'Отправить доступ',
            'copy' => 'Скопировать доступ',
        ];

        if ($type === 'update') {
            $iconType = 'edit';
        } elseif ($type === 'subcategory' || $type === 'add') {
            $iconType = 'add';
        } else {
            $iconType = $type;
        }

        return Html::a(self::icon($iconType), $url, array_merge([
            'class' => 'admin-icon-btn',
            'title' => $titles[$iconType] ?? $titles['view'],
            'aria-label' => $titles[$iconType] ?? $titles['view'],
        ], $options));
    }

    /**
     * @param array<string, array{label: string, url: array<int|string, int|string|null>|string}> $tabs
     */
    public static function pageTabs(
        array $tabs,
        string $activeKey,
        string $ariaLabel = 'Вкладки',
        bool $compact = false,
    ): string {
        $navClass = 'admin-page-tabs' . ($compact ? ' admin-page-tabs--compact' : '');
        $html = Html::beginTag('nav', ['class' => $navClass, 'aria-label' => $ariaLabel]);

        foreach ($tabs as $key => $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $label = trim((string)($tab['label'] ?? $key));
            $url = $tab['url'] ?? '#';
            $isActive = (string)$key === (string)$activeKey;

            $html .= Html::a(
                Html::encode($label),
                $url,
                [
                    'class' => 'admin-page-tabs__link' . ($isActive ? ' admin-page-tabs__link--active' : ''),
                ]
            );
        }

        return $html . Html::endTag('nav');
    }

    /**
     * @return array<string, callable>
     */
    public static function gridActionButtons(string $template = '{view} {update}'): array
    {
        $buttons = [];

        if (str_contains($template, '{view}')) {
            $buttons['view'] = static fn (string $url): string => self::actionIcon($url, 'view');
        }

        if (str_contains($template, '{update}')) {
            $buttons['update'] = static fn (string $url): string => self::actionIcon($url, 'update');
        }

        if (str_contains($template, '{delete}')) {
            $buttons['delete'] = static fn (string $url): string => self::actionIcon($url, 'delete', [
                'data-method' => 'post',
                'data-confirm' => 'Удалить запись?',
            ]);
        }

        return $buttons;
    }

    /**
     * @return array<string, callable(string, \app\models\User, int|string): string>
     */
    public static function dealerGridActionButtons(): array
    {
        $base = self::gridActionButtons('{view} {update}');

        $base['block'] = static function (string $url, \app\models\User $model): string {
            if ($model->is_blocked) {
                return self::actionIcon(['unblock', 'id' => $model->id], 'unblock', [
                    'data-method' => 'post',
                    'data-confirm' => 'Разблокировать дилера?',
                ]);
            }

            return self::actionIcon(['block', 'id' => $model->id], 'block', [
                'class' => 'admin-icon-btn admin-icon-btn--danger',
                'data-method' => 'post',
                'data-confirm' => 'Заблокировать дилера? Доступ к ЛКД будет прекращён.',
            ]);
        };

        $base['send'] = static fn (string $url, \app\models\User $model): string => self::actionIcon(
            ['send-credentials', 'id' => $model->id],
            'send',
            [
                'data-method' => 'post',
                'data-confirm' => 'Сгенерировать новый пароль и отправить на email?',
            ]
        );

        $base['copy'] = static fn (string $url, \app\models\User $model): string => self::actionIcon(
            ['copy-access', 'id' => $model->id],
            'copy',
            [
                'data-method' => 'post',
                'data-confirm' => 'Сгенерировать новый пароль для копирования?',
            ]
        );

        return $base;
    }

    /**
     * @param array<string, mixed> $fieldOptions
     * @param array<string, mixed> $inputOptions
     */
    public static function slugField(
        ActiveForm $form,
        Model $model,
        string $sourceAttribute,
        array $fieldOptions = [],
        array $inputOptions = [],
    ): ActiveField {
        $slugId = Html::getInputId($model, 'slug');
        $sourceId = Html::getInputId($model, $sourceAttribute);
        $sourceName = Html::getInputName($model, $sourceAttribute);

        $data = array_merge([
            'admin-slug' => '1',
            'admin-slug-source' => $sourceId,
            'admin-slug-source-name' => $sourceName,
        ], $inputOptions['data'] ?? []);
        unset($inputOptions['data']);

        $inputOptions = array_merge($inputOptions, ['data' => $data]);

        $form->getView()->registerJs(
            'if (window.adminSlugInit) { window.adminSlugInit(document.getElementById(' . json_encode($slugId) . ')); }',
            \yii\web\View::POS_END,
        );

        return $form->field($model, 'slug', $fieldOptions)->textInput($inputOptions);
    }

    /**
     * @param CatalogFabricColor[] $colors
     */
    public static function fabricColorSwatchesPreview(
        array $colors,
        int $visibleLimit = 3,
        bool $catalogColorsOnly = false
    ): string {
        $activeColors = array_values(array_filter($colors, static fn ($color): bool => (bool)$color->is_active));
        if ($activeColors === []) {
            return Html::tag('span', '—', ['class' => 'admin-muted']);
        }

        $html = Html::beginTag('div', ['class' => 'admin-fabric-swatches']);
        $shown = 0;
        foreach ($activeColors as $color) {
            if ($shown >= $visibleLimit) {
                break;
            }
            $html .= Html::tag('span', '', [
                'class' => 'admin-fabric-swatches__dot',
                'style' => $catalogColorsOnly
                    ? $color->getCatalogColorCircleStyle()
                    : $color->getSwatchCircleStyle(),
                'title' => $color->getDisplayLabel(),
            ]);
            $shown++;
        }

        $remaining = count($activeColors) - $shown;
        if ($remaining > 0) {
            $html .= Html::tag('span', '+' . $remaining, ['class' => 'admin-fabric-swatches__more']);
        }
        $html .= Html::endTag('div');

        return $html;
    }
}
