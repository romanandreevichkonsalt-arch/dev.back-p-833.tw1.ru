<?php

namespace app\modules\admin\helpers;

use app\models\PromoCodeTemplate;

class PromoTemplateOptions
{
    /**
     * @return array<int, string>
     */
    public static function activeLabels(): array
    {
        $templates = PromoCodeTemplate::find()
            ->where(['is_active' => true])
            ->orderBy(['code' => SORT_ASC])
            ->all();

        $options = [];
        foreach ($templates as $template) {
            $options[(int)$template->id] = self::formatLabel($template);
        }

        return $options;
    }

    /**
     * Активные промокоды + привязанный к форме (даже если снят с публикации).
     *
     * @return array<int, string>
     */
    public static function labelsForForm(?int $selectedTemplateId = null): array
    {
        $options = self::activeLabels();
        if ($selectedTemplateId !== null && $selectedTemplateId > 0 && !isset($options[$selectedTemplateId])) {
            $template = PromoCodeTemplate::findOne($selectedTemplateId);
            if ($template !== null) {
                $options[(int)$template->id] = self::formatLabel($template);
            }
        }

        ksort($options);

        return $options;
    }

    /**
     * Текст пустого пункта списка «Промокод» (новый код до сохранения или выбор существующего).
     *
     * @param object{promo_mode: string, promo_code: string, promo_title: string, promo_discount_percent: float|int|string, template_id: ?int} $form
     */
    public static function dropdownPrompt(object $form): string
    {
        if ($form->template_id !== null && (int)$form->template_id > 0) {
            return '— существующий —';
        }

        if ($form->promo_mode === 'create' && trim($form->promo_code) !== '') {
            $code = mb_strtoupper(trim($form->promo_code));
            $label = 'Новый: ' . $code;
            $title = trim($form->promo_title);
            if ($title !== '') {
                $label .= ' — ' . $title;
            }
            $label .= ' (' . $form->promo_discount_percent . '%)';

            return $label;
        }

        return '— существующий —';
    }

    private static function formatLabel(PromoCodeTemplate $template): string
    {
        return $template->code . ' — ' . $template->title;
    }
}
