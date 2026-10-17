<?php

namespace app\services\import\fabric;

final class FabricMediaDownloadResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $tempPath = null,
        public readonly ?string $downloadedFilename = null,
        public readonly ?string $errorMessage = null,
        public readonly int $httpCode = 0,
    ) {
    }

    public static function failed(string $message, int $httpCode = 0): self
    {
        return new self(false, errorMessage: $message, httpCode: $httpCode);
    }
}
