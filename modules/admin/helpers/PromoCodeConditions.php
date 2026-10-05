<?php

namespace app\modules\admin\helpers;

use app\models\PromoCodeTemplate;

class PromoCodeConditions
{
    /**
     * @return list<string>
     */
    private static function commonAccessLines(): array
    {
        return [
            'Только для авторизованных дилеров с выданным промокодом в «Мои бонусы».',
            'Гости и обычные покупатели не могут применить промокод.',
        ];
    }

    /**
     * @return list<array{code:string,title:string,type:string,typeLabel:string,editable:bool,lines:list<string>}>
     */
    public static function systemDefinitions(): array
    {
        return [
            [
                'code' => 'NOVINKA_{коллекция}',
                'title' => 'Новинка — выставочный образец',
                'type' => PromoCodeTemplate::TYPE_NOVELTY,
                'typeLabel' => PromoCodeTemplate::typeLabels()[PromoCodeTemplate::TYPE_NOVELTY],
                'editable' => false,
                'lines' => self::linesForType(PromoCodeTemplate::TYPE_NOVELTY),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function linesForTemplate(PromoCodeTemplate $template): array
    {
        $lines = self::linesForType($template->type, $template);

        if ($template->type === PromoCodeTemplate::TYPE_CUSTOM && $template->valid_until !== null && $template->valid_until !== '') {
            $lines[] = 'Действует до: ' . date('d.m.Y', strtotime((string)$template->valid_until));
        } elseif ($template->type === PromoCodeTemplate::TYPE_CUSTOM) {
            $lines[] = 'Без ограничения по сроку — действует, пока не использован.';
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function linesForType(string $type, ?PromoCodeTemplate $template = null): array
    {
        return match ($type) {
            PromoCodeTemplate::TYPE_EXHIBITION => array_merge(self::commonAccessLines(), [
                'Скидка на экспозиционный образец от дилерской цены.',
                'Промокод вводится в корзине, применяется однократно на дилера.',
                'Новым дилерам выдаётся автоматически при первой авторизации (раздел «Мои бонусы»).',
                'Администратор может выдать повторно из списка дилеров.',
                'После применения промокод деактивируется для данного дилера.',
                'Несовместим с кэшбеком.',
            ]),
            PromoCodeTemplate::TYPE_NOVELTY => array_merge(self::commonAccessLines(), [
                'Создаётся автоматически при пометке модели бейджем «Новинка».',
                'Код: NOVINKA_{slug коллекции}, срок действия — 30 календарных дней.',
                'Выдаётся всем дилерам при выборе «Создать и добавить всем» или кнопке «Выдать всем» в карточке промокода.',
                'Скидка на товары привязанной модели новинки (−10% от дилерской цены).',
                'Одноразовый для каждого дилера.',
                'Несовместим с кэшбеком.',
            ]),
            PromoCodeTemplate::TYPE_CUSTOM => array_merge(self::commonAccessLines(), [
                'Промокод создаётся администратором вручную.',
                'Выдаётся дилерам индивидуально (из карточки дилера или массово — по запросу).',
                'Вводится в корзине ЛКД.',
                'Несовместим с кэшбеком.',
            ]),
            default => [],
        };
    }

    public static function summary(PromoCodeTemplate $template): string
    {
        return self::validityLineForTemplate($template) ?? '—';
    }

    public static function validityLineForTemplate(PromoCodeTemplate $template): ?string
    {
        if ($template->valid_until !== null && $template->valid_until !== '') {
            return 'Действует до: ' . date('d.m.Y', strtotime((string)$template->valid_until));
        }

        return match ($template->type) {
            PromoCodeTemplate::TYPE_CUSTOM => 'Без ограничения по сроку — действует, пока не использован.',
            PromoCodeTemplate::TYPE_NOVELTY => '30 календарных дней',
            PromoCodeTemplate::TYPE_EXHIBITION => 'До однократного применения',
            default => null,
        };
    }

    public static function validityLineForSystemType(string $type): ?string
    {
        return match ($type) {
            PromoCodeTemplate::TYPE_NOVELTY => '30 календарных дней',
            PromoCodeTemplate::TYPE_EXHIBITION => 'До однократного применения',
            default => null,
        };
    }
}
