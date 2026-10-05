<?php

namespace tests\unit\services;

use app\services\vacancy\VacancyAboutJobSelector;
use Codeception\Test\Unit;

class VacancyAboutJobSelectorTest extends Unit
{
    public function testPicksOnePerGroupFirst(): void
    {
        $grouped = [
            'production' => [$this->item(1), $this->item(2)],
            'product_design' => [$this->item(3)],
            'logistics' => [$this->item(4)],
            'client_experience' => [$this->item(5)],
        ];

        $picked = VacancyAboutJobSelector::select(
            $grouped,
            ['production', 'product_design', 'logistics', 'client_experience', 'management'],
            4
        );

        $this->assertSame([1, 3, 4, 5], $this->ids($picked));
    }

    public function testFillsRemainingFromSameGroups(): void
    {
        $grouped = [
            'production' => [$this->item(1), $this->item(2)],
            'product_design' => [$this->item(3)],
        ];

        $picked = VacancyAboutJobSelector::select(
            $grouped,
            ['production', 'product_design', 'logistics'],
            4
        );

        $this->assertSame([1, 3, 2], $this->ids($picked));
    }

    public function testReturnsAllWhenLessThanLimit(): void
    {
        $grouped = [
            'production' => [$this->item(1)],
            'product_design' => [$this->item(2)],
        ];

        $picked = VacancyAboutJobSelector::select($grouped, ['production', 'product_design'], 4);

        $this->assertCount(2, $picked);
    }

    private function item(int $id): object
    {
        return (object)['id' => $id];
    }

    /**
     * @param array<int, object{id: int|string|null}> $items
     * @return int[]
     */
    private function ids(array $items): array
    {
        return array_map(static fn (object $item): int => (int)$item->id, $items);
    }
}
