<?php

namespace tests\unit\services;

use app\services\search\SearchQueryResolver;
use app\services\search\SearchRanker;
use Codeception\Test\Unit;

class SearchQueryResolverTest extends Unit
{
    private SearchQueryResolver $resolver;

  protected function _before(): void
    {
        $this->resolver = new SearchQueryResolver();
    }

    public function testPrefixExpansionForShortQuery(): void
    {
        $vocabulary = ['диван', 'диваны', 'кресло', 'стол', 'торвалль'];

        $result = $this->resolver->resolve('див', $vocabulary);

        verify($result['correction'])->equals('диван');
        verify($result['effective'])->equals('диван');
        verify($result['mode'])->equals('prefix');
    }

    public function testDeadZoneForAmbiguousShortPrefix(): void
    {
        $vocabulary = ['стол', 'столы', 'торвалль', 'толл'];

        $result = $this->resolver->resolve('тол', $vocabulary);

        verify($result['correction'])->null();
        verify($result['effective'])->equals('тол');
        verify($result['mode'])->equals('literal');
    }

    public function testTypoCorrectionForLongerQuery(): void
    {
        $vocabulary = ['стол', 'столы', 'торвалль'];

        $result = $this->resolver->resolve('толл', $vocabulary);

        verify($result['correction'])->equals('стол');
        verify($result['mode'])->equals('corrected');
    }

    public function testExactMatchDoesNotSetCorrection(): void
    {
        $vocabulary = ['турин', 'тур', 'диван'];

        $result = $this->resolver->resolve('турин', $vocabulary);

        verify($result['correction'])->null();
        verify($result['mode'])->equals('exact');
    }

    public function testDoesNotExpandToNearTypoPrefix(): void
    {
        $vocabulary = ['стол', 'толл', 'торвалль'];

        $result = $this->resolver->resolve('тол', $vocabulary);

        verify($result['correction'])->null();
        verify($result['mode'])->equals('literal');
    }
}
