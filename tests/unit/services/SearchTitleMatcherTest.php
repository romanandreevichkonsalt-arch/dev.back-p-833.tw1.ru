<?php

namespace tests\unit\services;

use app\services\search\SearchQueryResolver;
use app\services\search\SearchTitleMatcher;
use Codeception\Test\Unit;

class SearchTitleMatcherTest extends Unit
{
    private SearchTitleMatcher $matcher;

    private SearchQueryResolver $resolver;

    /** @var list<array<string, mixed>> */
    private array $products = [
        [
            'id' => 'uglovoy-artemida-oskar-2',
            'slug' => 'uglovoy-artemida-oskar-2',
            'title' => 'Угловой диван Артемида Бежевый Oskar 2',
            'type' => 'Угловой диван',
            'collection' => 'Артемида',
        ],
        [
            'id' => 'pryamoy-artemida-sydney',
            'slug' => 'pryamoy-artemida-sydney',
            'title' => 'Прямой диван Артемида Серый Sydney 3',
            'type' => 'Прямой диван',
            'collection' => 'Артемида',
        ],
        [
            'id' => 'pryamoy-adriano',
            'slug' => 'pryamoy-adriano',
            'title' => 'Прямой диван Адриано Белый Riz 0',
            'type' => 'Прямой диван',
            'collection' => 'Адриано',
        ],
    ];

    protected function _before(): void
    {
        $this->resolver = new SearchQueryResolver();
        $this->matcher = new SearchTitleMatcher($this->resolver);
    }

    public function testExactTitleMatch(): void
    {
        $vocabulary = $this->resolver->buildTokenVocabulary([], [], $this->products);
        $result = $this->matcher->match(
            $this->products,
            'Угловой диван Артемида Бежевый Oskar 2',
            $vocabulary
        );

        verify($result['matchType'])->equals(SearchTitleMatcher::MATCH_EXACT);
        verify($result['products'])->arrayCount(1);
        verify($result['products'][0]['id'])->equals('uglovoy-artemida-oskar-2');
        verify($result['correction'])->null();
    }

    public function testTokenFixTypoFindsExactTitle(): void
    {
        $vocabulary = $this->resolver->buildTokenVocabulary([], [], $this->products);
        $result = $this->matcher->match(
            $this->products,
            'Угловой диаан Артемида Бежевый Oskar 2',
            $vocabulary
        );

        verify($result['matchType'])->equals(SearchTitleMatcher::MATCH_EXACT);
        verify($result['products'][0]['id'])->equals('uglovoy-artemida-oskar-2');
        verify($result['correction'])->equals('угловой диван артемида бежевый oskar 2');
    }

    public function testCascadeByPartialTitle(): void
    {
        $vocabulary = $this->resolver->buildTokenVocabulary([], [], $this->products);
        $result = $this->matcher->match(
            $this->products,
            'Артемида Бежевый Oskar',
            $vocabulary
        );

        verify($result['matchType'])->equals(SearchTitleMatcher::MATCH_CASCADE);
        verify($result['products'][0]['id'])->equals('uglovoy-artemida-oskar-2');
    }

    public function testSingleWordCollectionUsesCascade(): void
    {
        $vocabulary = $this->resolver->buildTokenVocabulary([], [], $this->products);
        $result = $this->matcher->match($this->products, 'Адриано', $vocabulary);

        verify($result['matchType'])->equals(SearchTitleMatcher::MATCH_CASCADE);
        verify($result['products'])->notEmpty();
        verify($result['products'][0]['id'])->equals('pryamoy-adriano');
    }

    public function testExactMatchSkipsRoundRobin(): void
    {
        verify($this->matcher->shouldUseRoundRobin(SearchTitleMatcher::MATCH_EXACT, 'угловой диван'))->false();
        verify($this->matcher->shouldUseRoundRobin(SearchTitleMatcher::MATCH_CASCADE, 'артемида бежевый oskar 2'))->false();
        verify($this->matcher->shouldUseRoundRobin(SearchTitleMatcher::MATCH_CASCADE, 'артемида'))->true();
    }

    public function testExactMatchBySlug(): void
    {
        $vocabulary = $this->resolver->buildTokenVocabulary([], [], $this->products);
        $result = $this->matcher->match(
            $this->products,
            'uglovoy-artemida-oskar-2',
            $vocabulary
        );

        verify($result['matchType'])->equals(SearchTitleMatcher::MATCH_EXACT);
        verify($result['products'][0]['id'])->equals('uglovoy-artemida-oskar-2');
    }
}
