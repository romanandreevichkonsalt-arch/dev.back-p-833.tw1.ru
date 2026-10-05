<?php

namespace app\modules\admin\helpers;

final class DealerManagerWorkHoursHelper
{
    /** @var list<string> */
    private const DAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    /**
     * @return array<string, string>
     */
    public static function dayOptions(): array
    {
        $options = [];
        foreach (self::DAYS as $day) {
            $options[$day] = $day;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function timeOptions(): array
    {
        $options = [];
        for ($hour = 8; $hour <= 21; $hour++) {
            foreach ([0, 30] as $minute) {
                if ($hour === 21 && $minute > 0) {
                    break;
                }
                $value = sprintf('%d:%02d', $hour, $minute);
                $options[$value] = $value;
            }
        }

        return $options;
    }

    /**
     * @return array{dayFrom: string, dayTo: string, timeFrom: string, timeTo: string}
     */
    public static function defaults(): array
    {
        return [
            'dayFrom' => 'Пн',
            'dayTo' => 'Пт',
            'timeFrom' => '9:00',
            'timeTo' => '18:00',
        ];
    }

    /**
     * @return array{dayFrom: string, dayTo: string, timeFrom: string, timeTo: string}
     */
    public static function parse(?string $workHours): array
    {
        $defaults = self::defaults();
        $workHours = trim((string)$workHours);
        if ($workHours === '') {
            return $defaults;
        }

        if (preg_match(
            '/^(Пн|Вт|Ср|Чт|Пт|Сб|Вс)[–-](Пн|Вт|Ср|Чт|Пт|Сб|Вс)\s+(\d{1,2}:\d{2})[–-](\d{1,2}:\d{2})$/u',
            $workHours,
            $matches,
        )) {
            return [
                'dayFrom' => $matches[1],
                'dayTo' => $matches[2],
                'timeFrom' => self::normalizeTime($matches[3]),
                'timeTo' => self::normalizeTime($matches[4]),
            ];
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $parts
     */
    public static function format(array $parts): string
    {
        $parts = array_merge(self::defaults(), $parts);
        $dayFrom = (string)$parts['dayFrom'];
        $dayTo = (string)$parts['dayTo'];
        if (!in_array($dayFrom, self::DAYS, true)) {
            $dayFrom = self::defaults()['dayFrom'];
        }
        if (!in_array($dayTo, self::DAYS, true)) {
            $dayTo = self::defaults()['dayTo'];
        }

        return sprintf(
            '%s–%s %s–%s',
            $dayFrom,
            $dayTo,
            self::normalizeTime((string)$parts['timeFrom']),
            self::normalizeTime((string)$parts['timeTo']),
        );
    }

    /**
     * @param array<string, mixed>|null $parts
     */
    public static function formatFromPost(?array $parts): ?string
    {
        if ($parts === null) {
            return null;
        }

        return self::format($parts);
    }

    private static function normalizeTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return sprintf('%d:%02d', (int)$matches[1], (int)$matches[2]);
        }

        return trim($time);
    }
}
