<?php

namespace app\services\import\catalog;

use app\services\catalog\CatalogFilterFunction;

class CatalogModelImportRowDto
{
    /**
     * @param array<int, int> $pricesByCategoryNumber category number => price in rubles
     * @param list<string> $fabricCollectionNames
     * @param list<string> $galleryPhotoUrls
     * @param list<string> $dimensionPhotoUrls
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $directionLabel,
        public readonly string $collectionName,
        public readonly string $displayLabel,
        public readonly string $productTitlePart,
        public readonly string $categoryLabel,
        public readonly string $subcategoryLabel,
        public readonly bool $isModule,
        public readonly array $pricesByCategoryNumber,
        public readonly array $fabricCollectionNames = [],
        public readonly ?string $fittingRoomUrl = null,
        public readonly ?string $polygons3d = null,
        public readonly ?string $file3dUrl = null,
        public readonly ?string $videoUrl = null,
        public readonly array $galleryPhotoUrls = [],
        public readonly array $dimensionPhotoUrls = [],
        public readonly ?string $dimensionPhotoFolderUrl = null,
        public readonly ?string $subtitle = null,
        public readonly ?string $description = null,
        public readonly ?string $overallSize = null,
        public readonly ?string $seatDepth = null,
        public readonly ?string $seatHeight = null,
        public readonly ?string $armrestWidth = null,
        public readonly ?string $legHeight = null,
        public readonly ?string $frameSpec = null,
        public readonly ?string $mechanism = null,
        public readonly ?string $fillingSpec = null,
        public readonly ?string $additional = null,
        public readonly ?string $frame = null,
        public readonly ?string $foundation = null,
        public readonly ?string $filling = null,
        public readonly ?string $upholstery = null,
        public readonly ?string $supports = null,
        public readonly string $filterFunction = CatalogFilterFunction::NONE,
        public readonly ?string $sleepingPlaceSize = null,
    ) {
    }
}
