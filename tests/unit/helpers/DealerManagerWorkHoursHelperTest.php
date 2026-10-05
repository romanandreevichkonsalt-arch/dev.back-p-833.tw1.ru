<?php

namespace tests\unit\helpers;

use app\modules\admin\helpers\DealerManagerWorkHoursHelper;
use Codeception\Test\Unit;

class DealerManagerWorkHoursHelperTest extends Unit
{
    public function testFormatDefaultSchedule(): void
    {
        $this->assertSame(
            'Пн–Пт 9:00–18:00',
            DealerManagerWorkHoursHelper::format(DealerManagerWorkHoursHelper::defaults()),
        );
    }

    public function testParseKnownSchedule(): void
    {
        $parts = DealerManagerWorkHoursHelper::parse('Пн–Пт 10:00–19:00');

        $this->assertSame('Пн', $parts['dayFrom']);
        $this->assertSame('Пт', $parts['dayTo']);
        $this->assertSame('10:00', $parts['timeFrom']);
        $this->assertSame('19:00', $parts['timeTo']);
    }

    public function testRoundTrip(): void
    {
        $formatted = DealerManagerWorkHoursHelper::format([
            'dayFrom' => 'Вт',
            'dayTo' => 'Сб',
            'timeFrom' => '8:30',
            'timeTo' => '17:30',
        ]);

        $this->assertSame('Вт–Сб 8:30–17:30', $formatted);
        $this->assertSame(
            ['dayFrom' => 'Вт', 'dayTo' => 'Сб', 'timeFrom' => '8:30', 'timeTo' => '17:30'],
            DealerManagerWorkHoursHelper::parse($formatted),
        );
    }
}
