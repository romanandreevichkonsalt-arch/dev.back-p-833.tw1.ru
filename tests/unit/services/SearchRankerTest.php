<?php

namespace tests\unit\services;

use app\services\search\SearchRanker;
use Codeception\Test\Unit;

class SearchRankerTest extends Unit
{
    private SearchRanker $ranker;

    /** @var list<array<string, mixed>> */
    private array $products = [
        [
            'id' => 'turin',
            'title' => 'Диван Турин',
            'type' => 'Прямой',
            'collection' => 'Коллекция А+',
        ],
        [
            'id' => 'toscana',
            'title' => 'Диван Тоскана',
            'type' => 'Прямой',
            'collection' => 'Коллекция Softness',
        ],
        [
            'id' => 'stenli',
            'title' => 'Диван Стенли',
            'type' => 'Прямой',
            'collection' => 'Коллекция А+',
        ],
    ];

    protected function _before(): void
    {
        $this->ranker = new SearchRanker();
    }

    public function testProductSearchRequiresMinimumLength(): void
    {
        verify($this->ranker->matchProducts($this->products, 'из', 'из'))->empty();
    }

    public function testProductSearchRespectsLimit(): void
    {
        $products = $this->ranker->matchProducts($this->products, 'диван', 'диван', 1);

        verify(count($products))->equals(1);
    }

    public function testProductSearchWithoutLimitReturnsAllMatches(): void
    {
        $products = $this->ranker->matchProducts($this->products, 'диван', 'диван');

        verify(count($products))->equals(3);
    }

    public function testFindsProductByExactModelName(): void
    {
        $products = $this->ranker->matchProducts($this->products, 'турин', 'турин');

        verify($products)->notEmpty();
        verify($products[0]['id'])->equals('turin');
    }

    public function testFindsProductByCorrectedPrefix(): void
    {
        $products = $this->ranker->matchProducts($this->products, 'турин', 'тур');

        verify($products)->notEmpty();
        verify($products[0]['id'])->equals('turin');
    }

    public function testFiltersCategoriesByQuery(): void
    {
        $categories = [
            ['id' => 'sofas', 'label' => 'Диваны', 'href' => '/catalog/divany'],
            ['id' => 'chairs', 'label' => 'Кресла', 'href' => '/catalog/kresla'],
        ];

        $matched = $this->ranker->matchCategories($categories, 'диван', 'див');

        verify(count($matched))->equals(1);
        verify($matched[0]['id'])->equals('sofas');
    }

    public function testSuggestionsFromFrequentQueries(): void
    {
        $frequent = ['прямой диван', 'угловой диван', 'матрас'];

        $suggestions = $this->ranker->matchSuggestions(
            $frequent,
            ['диван', 'матрас'],
            'диван',
            'див',
            'диван'
        );

        verify($suggestions)->notEmpty();
        verify(in_array('прямой диван', $suggestions, true))->true();
    }
}
