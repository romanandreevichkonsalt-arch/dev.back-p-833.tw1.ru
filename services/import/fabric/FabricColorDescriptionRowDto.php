<?php

namespace app\services\import\fabric;

class FabricColorDescriptionRowDto
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $fabricCollectionName,
        public readonly string $designCode,
        public readonly string $description,
    ) {
    }
}
