<?php

namespace app\services\import\surface;

readonly class SurfaceMaterialRegistryRowDto
{
    public function __construct(
        public int $rowNumber,
        public ?int $registryNumber,
        public string $materialType,
        public string $name,
        public ?string $appliedModelsText,
        public ?string $photoUrl,
        public ?string $textureUrl,
        public ?string $description,
    ) {
    }

    public function isSkippable(): bool
    {
        if ($this->name === '') {
            return true;
        }

        $lowerName = mb_strtolower($this->name);
        if (in_array($lowerName, ['название / тонировка', 'название/тонировка'], true)) {
            return true;
        }

        if (mb_strtolower($this->materialType) === 'тип') {
            return true;
        }

        return false;
    }

    public function rowHash(): string
    {
        return hash('sha256', implode('|', [
            $this->materialType,
            $this->name,
            $this->appliedModelsText ?? '',
            $this->photoUrl ?? '',
            $this->textureUrl ?? '',
            $this->description ?? '',
        ]));
    }

    /**
     * @return array<string, scalar|null>
     */
    public function fingerprint(): array
    {
        return [
            'material_type' => $this->materialType,
            'name' => $this->name,
            'applied_models_text' => $this->appliedModelsText,
            'description' => $this->description,
            'source_photo_url' => $this->photoUrl,
            'source_texture_url' => $this->textureUrl,
        ];
    }
}
