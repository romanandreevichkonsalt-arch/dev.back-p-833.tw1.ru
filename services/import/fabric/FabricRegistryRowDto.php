<?php

namespace app\services\import\fabric;

class FabricRegistryRowDto
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $materialKind,
        public readonly string $collectionName,
        public readonly string $colorName,
        public readonly ?string $composition,
        public readonly ?string $priceCategoryLabelA,
        public readonly ?string $priceCategoryLabelLine1,
        public readonly ?string $texture,
        public readonly ?string $colorLabel,
        public readonly ?int $martindale,
        public readonly ?string $properties,
        public readonly ?int $rollWidthCm,
        public readonly ?int $densityGsm,
        public readonly ?string $textureUrl,
        public readonly bool $isRecommendedFabric,
        public readonly ?int $positionNumber,
        public readonly ?string $description,
        public readonly ?string $importComment,
    ) {
    }

    public function isSkippable(): bool
    {
        if (trim($this->collectionName) === '' || trim($this->colorName) === '') {
            return true;
        }

        $haystack = mb_strtolower($this->collectionName . ' ' . $this->colorName);
        if (str_contains($haystack, 'disk.yandex') && str_contains($haystack, 'пример')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function fingerprint(): array
    {
        return [
            'material_kind' => $this->materialKind,
            'collection' => $this->collectionName,
            'color_name' => $this->colorName,
            'composition' => $this->composition,
            'texture' => $this->texture,
            'color' => $this->colorLabel,
            'martindale' => $this->martindale,
            'properties' => $this->properties,
            'roll_width_cm' => $this->rollWidthCm,
            'density_gsm' => $this->densityGsm,
            'is_recommended_fabric' => $this->isRecommendedFabric,
            'position_number' => $this->positionNumber,
            'description' => $this->description,
            'import_comment' => $this->importComment,
        ];
    }

    public function rowHash(): string
    {
        return hash('sha256', json_encode($this->fingerprint(), JSON_UNESCAPED_UNICODE));
    }
}
