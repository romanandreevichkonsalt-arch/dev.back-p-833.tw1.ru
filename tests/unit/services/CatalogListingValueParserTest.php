<?php

namespace tests\unit\services;

use app\services\catalog\CatalogFilterFunction;
use app\services\catalog\CatalogListingValueParser;
use Codeception\Test\Unit;

class CatalogListingValueParserTest extends Unit
{
    public function testParseOverallSizeMm(): void
    {
        verify(CatalogListingValueParser::parseOverallSizeMm('2500×860×1200'))->equals([
            'width' => 2500,
            'height' => 860,
            'depth' => 1200,
        ]);
        verify(CatalogListingValueParser::parseOverallSizeMm('220x85x95'))->equals([
            'width' => 220,
            'height' => 85,
            'depth' => 95,
        ]);
        verify(CatalogListingValueParser::parseOverallSizeMm(null))->null();
        verify(CatalogListingValueParser::parseOverallSizeMm('invalid'))->null();
    }

    public function testParseOverallSizeMmWithCornerDepth(): void
    {
        verify(CatalogListingValueParser::parseOverallSizeMm('2500×860×1200×900'))->equals([
            'width' => 2500,
            'height' => 860,
            'depth' => 1200,
            'cornerDepth' => 900,
        ]);
        verify(CatalogListingValueParser::parseOverallSizeMm('2500×860х1200'))->equals([
            'width' => 2500,
            'height' => 860,
            'depth' => 1200,
        ]);
    }

    public function testParseFilterFunction(): void
    {
        verify(CatalogListingValueParser::parseFilterFunction('Со спальным местом'))
            ->equals(CatalogFilterFunction::WITH_SLEEPING);
        verify(CatalogListingValueParser::parseFilterFunction('Без спального места'))
            ->equals(CatalogFilterFunction::NO_SLEEPING);
        verify(CatalogListingValueParser::parseFilterFunction('Раскладной'))
            ->equals(CatalogFilterFunction::FOLDABLE);
        verify(CatalogListingValueParser::parseFilterFunction(null))
            ->equals(CatalogFilterFunction::NONE);
    }

    public function testParseSleepingPlaceSizeMm(): void
    {
        verify(CatalogListingValueParser::parseSleepingPlaceSizeMm('100×100'))->equals([
            'width' => 100,
            'depth' => 100,
        ]);
        verify(CatalogListingValueParser::parseSleepingPlaceSizeMm('900x2000'))->equals([
            'width' => 900,
            'depth' => 2000,
        ]);
        verify(CatalogListingValueParser::parseSleepingPlaceSizeMm(''))->null();
    }

    public function testParseSleepingPlaceFilter(): void
    {
        verify(CatalogListingValueParser::parseSleepingPlaceFilter('Со спальным местом'))->true();
        verify(CatalogListingValueParser::parseSleepingPlaceFilter('да'))->false();
        verify(CatalogListingValueParser::parseSleepingPlaceFilter('нет'))->false();
        verify(CatalogListingValueParser::parseSleepingPlaceFilter(null))->false();
        verify(CatalogListingValueParser::parseSleepingPlaceFilter(''))->false();
    }

    public function testSyncDimensionAttributes(): void
    {
        $model = new class {
            public ?string $overall_size = '2500×860×1200';
            public ?int $width_mm = null;
            public ?int $height_mm = null;
            public ?int $depth_mm = null;
            public ?int $corner_depth_mm = null;
        };

        CatalogListingValueParser::syncDimensionAttributes($model);
        verify($model->width_mm)->equals(2500);
        verify($model->height_mm)->equals(860);
        verify($model->depth_mm)->equals(1200);
        verify($model->overall_size)->equals('2500×860×1200');
    }

    public function testBuildOverallSizeStringFromFields(): void
    {
        $model = new class {
            public ?string $overall_size = '';
            public ?int $width_mm = 2500;
            public ?int $height_mm = 860;
            public ?int $depth_mm = 1200;
            public ?int $corner_depth_mm = 900;
        };

        CatalogListingValueParser::syncDimensionAttributes($model);
        verify($model->overall_size)->equals('2500×860×1200×900');
    }

    public function testParsePriceAmount(): void
    {
        verify(CatalogListingValueParser::parsePriceAmount('120 000 ₽'))->equals(120000);
        verify(CatalogListingValueParser::parsePriceAmount(''))->null();
        verify(CatalogListingValueParser::parsePriceAmount('по запросу'))->null();
    }
}
