<?php

namespace tests\unit\services;

use app\services\catalog\CatalogRequestParams;
use Codeception\Test\Unit;

class CatalogRequestParamsTest extends Unit
{
    public function testParseRepeatedColorParamsFromQueryString(): void
    {
        $values = CatalogRequestParams::parseMultiValueFromQueryString(
            'color=bezhevyy&color=zelenyy&page=1',
            'color'
        );

        verify($values)->equals(['bezhevyy', 'zelenyy']);
    }

    public function testParseBracketedColorParamsFromQueryString(): void
    {
        $values = CatalogRequestParams::parseMultiValueFromQueryString(
            'color[]=bezhevyy&color[]=zelenyy',
            'color'
        );

        verify($values)->equals(['bezhevyy', 'zelenyy']);
    }

    public function testParseCommaSeparatedColorParamFromQueryString(): void
    {
        $values = CatalogRequestParams::parseMultiValueFromQueryString(
            'color=bezhevyy,zelenyy',
            'color'
        );

        verify($values)->equals(['bezhevyy', 'zelenyy']);
    }

    public function testApplyMultiValueFiltersMergesRepeatedQueryWithPhpLastValue(): void
    {
        $params = CatalogRequestParams::applyMultiValueFilters(
            ['color' => 'zelenyy', 'subcategory' => 'pryamoy-divan'],
            'subcategory=pryamoy-divan&color=bezhevyy&color=zelenyy'
        );

        verify($params['color'])->equals(['zelenyy', 'bezhevyy']);
        verify($params['subcategory'])->equals('pryamoy-divan');
    }

    public function testExpandFilterValueSplitsCommaSeparatedTokens(): void
    {
        verify(CatalogRequestParams::expandFilterValue('a,b'))->equals(['a', 'b']);
        verify(CatalogRequestParams::expandFilterValue('single'))->equals(['single']);
    }

    public function testCollectionTrueEnablesCollectionGroups(): void
    {
        $params = (new CatalogRequestParams())->normalize(['collection' => 'true']);

        verify($params['collectionGroups'] ?? null)->true();
        verify(array_key_exists('collection', $params))->false();
    }

    public function testCollectionFalseDisablesCollectionGroups(): void
    {
        $params = (new CatalogRequestParams())->normalize(['collection' => 'false']);

        verify($params['collectionGroups'] ?? null)->false();
    }

    public function testNormalizeItemsPerGroupUsesDefaultAndMax(): void
    {
        verify(CatalogRequestParams::normalizeItemsPerGroup([]))->equals(3);
        verify(CatalogRequestParams::normalizeItemsPerGroup(['itemsPerGroup' => 5]))->equals(5);
        verify(CatalogRequestParams::normalizeItemsPerGroup(['itemsPerGroup' => 99]))->equals(12);
        verify(CatalogRequestParams::normalizeItemsPerGroup(['itemsPerGroup' => 0]))->equals(1);
    }
}
