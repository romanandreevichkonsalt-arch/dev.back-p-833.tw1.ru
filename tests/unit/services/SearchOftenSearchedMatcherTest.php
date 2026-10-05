<?php

namespace tests\unit\services;

use app\services\search\SearchOftenSearchedMatcher;
use Codeception\Test\Unit;

class SearchOftenSearchedMatcherTest extends Unit
{
    public function testRequiresMinimumQueryLength(): void
    {
        $matcher = new SearchOftenSearchedMatcher();

        verify($matcher->matchAgainst('ди', [], [], []))->empty();
    }

    public function testMatchesStaticFrequentQueries(): void
    {
        $matcher = new SearchOftenSearchedMatcher();

        $items = $matcher->matchAgainst(
            'див',
            [
                ['id' => 'divany', 'label' => 'Диваны', 'href' => '/catalog?subcategory=divany'],
            ],
            [],
            ['прямой диван', 'матрас']
        );

        verify($items)->notEmpty();
        verify($items[0]['label'])->equals('Диваны');
        verify($items[0]['href'])->equals('/catalog?subcategory=divany');
    }
}
