<?php

namespace tests\unit\services;

use app\services\moodboard\MoodboardPagination;
use Codeception\Test\Unit;

class MoodboardPaginationTest extends Unit
{
    public function testMeta(): void
    {
        $meta = MoodboardPagination::meta(42, 2, 3);
        verify($meta)->equals([
            'total' => 42,
            'page' => 2,
            'limit' => 3,
        ]);
    }
}
