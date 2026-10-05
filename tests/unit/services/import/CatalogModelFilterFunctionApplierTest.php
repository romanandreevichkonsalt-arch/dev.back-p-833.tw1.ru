<?php

namespace tests\unit\services\import;

use app\services\catalog\CatalogFilterFunction;
use app\services\import\catalog\CatalogModelFilterFunctionApplier;
use Codeception\Test\Unit;

class CatalogModelFilterFunctionApplierTest extends Unit
{
    private CatalogModelFilterFunctionApplier $applier;

    protected function _before(): void
    {
        $this->applier = new CatalogModelFilterFunctionApplier();
    }

    public function testAppliesSleepingPlaceForDivanSlug(): void
    {
        $model = new class {
            public bool $has_sleeping_place = false;
            public bool $is_foldable = false;
            public ?int $sleeping_place_width_mm = null;
            public ?int $sleeping_place_depth_mm = null;
        };

        $this->applier->apply($model, CatalogFilterFunction::WITH_SLEEPING, 'divan');
        $this->applier->applySleepingPlaceSize($model, CatalogFilterFunction::WITH_SLEEPING, 'divan', null);

        verify($model->has_sleeping_place)->true();
        verify($model->sleeping_place_width_mm)->null();
        verify($model->sleeping_place_depth_mm)->null();
    }

    public function testAppliesFoldableForKresloSlug(): void
    {
        $model = new class {
            public bool $has_sleeping_place = false;
            public bool $is_foldable = false;
            public ?int $sleeping_place_width_mm = null;
            public ?int $sleeping_place_depth_mm = null;
        };

        $this->applier->apply($model, CatalogFilterFunction::FOLDABLE, 'kreslo');

        verify($model->is_foldable)->true();
        verify($model->has_sleeping_place)->false();
    }

    public function testAppliesSleepingPlaceForSofa(): void
    {
        $model = new class {
            public bool $has_sleeping_place = false;
            public bool $is_foldable = true;
            public ?int $sleeping_place_width_mm = null;
            public ?int $sleeping_place_depth_mm = null;
        };

        $this->applier->apply($model, CatalogFilterFunction::WITH_SLEEPING, 'sofa');
        $this->applier->applySleepingPlaceSize($model, CatalogFilterFunction::WITH_SLEEPING, 'sofa', '100×100');

        verify($model->has_sleeping_place)->true();
        verify($model->is_foldable)->false();
        verify($model->sleeping_place_width_mm)->equals(100);
        verify($model->sleeping_place_depth_mm)->equals(100);
    }

    public function testAppliesFoldableForArmchair(): void
    {
        $model = new class {
            public bool $has_sleeping_place = true;
            public bool $is_foldable = false;
            public ?int $sleeping_place_width_mm = null;
            public ?int $sleeping_place_depth_mm = null;
        };

        $this->applier->apply($model, CatalogFilterFunction::FOLDABLE, 'armchair');
        $this->applier->applySleepingPlaceSize($model, CatalogFilterFunction::FOLDABLE, 'armchair', '900x2000');

        verify($model->has_sleeping_place)->false();
        verify($model->is_foldable)->true();
        verify($model->sleeping_place_width_mm)->equals(900);
        verify($model->sleeping_place_depth_mm)->equals(2000);
    }

    public function testIgnoresMismatchAndClearsSizes(): void
    {
        $model = new class {
            public bool $has_sleeping_place = true;
            public bool $is_foldable = false;
            public ?int $sleeping_place_width_mm = 100;
            public ?int $sleeping_place_depth_mm = 100;
        };

        $this->applier->apply($model, CatalogFilterFunction::WITH_SLEEPING, 'armchair');
        $this->applier->applySleepingPlaceSize($model, CatalogFilterFunction::WITH_SLEEPING, 'armchair', '100×100');

        verify($model->has_sleeping_place)->true();
        verify($model->is_foldable)->false();
        verify($model->sleeping_place_width_mm)->null();
        verify($model->sleeping_place_depth_mm)->null();
    }

    public function testNoSleepingClearsSizesOnly(): void
    {
        $model = new class {
            public bool $has_sleeping_place = true;
            public bool $is_foldable = false;
            public ?int $sleeping_place_width_mm = 100;
            public ?int $sleeping_place_depth_mm = 100;
        };

        $this->applier->apply($model, CatalogFilterFunction::NO_SLEEPING, 'sofa');
        $this->applier->applySleepingPlaceSize($model, CatalogFilterFunction::NO_SLEEPING, 'sofa', '100×100');

        verify($model->has_sleeping_place)->true();
        verify($model->sleeping_place_width_mm)->null();
        verify($model->sleeping_place_depth_mm)->null();
    }
}
