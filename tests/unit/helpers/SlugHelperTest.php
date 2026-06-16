<?php

namespace tests\unit\helpers;

use app\helpers\SlugHelper;
use Codeception\Test\Unit;

class SlugHelperTest extends Unit
{
    public function testTransliteration(): void
    {
        $this->assertSame('divany-i-kresla', SlugHelper::fromString('Диваны и кресла'));
        $this->assertSame('uglovye-divany', SlugHelper::fromString('Угловые диваны'));
    }
}
