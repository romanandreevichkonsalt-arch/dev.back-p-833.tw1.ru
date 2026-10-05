<?php

namespace app\services\media;

final class ListingFrameData
{
    public function __construct(
        public readonly float $scale,
        public readonly float $offsetX,
        public readonly float $offsetY,
        public readonly int $version = 1,
    ) {
    }

    /**
     * @param array<string, mixed>|null $raw
     */
    public static function fromArray(?array $raw): ?self
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return new self(
            scale: self::normalizeScale($raw['scale'] ?? 1),
            offsetX: (float)($raw['offsetX'] ?? 0),
            offsetY: (float)($raw['offsetY'] ?? 0),
            version: (int)($raw['version'] ?? 1),
        );
    }

    public static function defaults(): self
    {
        return new self(scale: 1.0, offsetX: 0.0, offsetY: 0.0);
    }

    /**
     * Стартовый кадр без сохранённых настроек: вписать фото целиком (без cover-zoom).
     */
    public static function defaultsForImage(int $sourceWidth, int $sourceHeight, int $tileWidth, int $tileHeight): self
    {
        return new self(
            scale: self::initialScaleForImage($sourceWidth, $sourceHeight, $tileWidth, $tileHeight),
            offsetX: 0.0,
            offsetY: 0.0,
        );
    }

    public static function initialScaleForImage(
        int $sourceWidth,
        int $sourceHeight,
        int $tileWidth,
        int $tileHeight,
    ): float {
        if ($sourceWidth <= 0 || $sourceHeight <= 0 || $tileWidth <= 0 || $tileHeight <= 0) {
            return 1.0;
        }

        $cover = max($tileWidth / $sourceWidth, $tileHeight / $sourceHeight);
        $contain = min($tileWidth / $sourceWidth, $tileHeight / $sourceHeight);
        if ($cover <= 0.0) {
            return 1.0;
        }

        return self::normalizeScale($contain / $cover);
    }

    /**
     * @return array{scale: float, offsetX: float, offsetY: float, version: int}
     */
    public function toArray(): array
    {
        return [
            'scale' => $this->scale,
            'offsetX' => $this->offsetX,
            'offsetY' => $this->offsetY,
            'version' => $this->version,
        ];
    }

    public function encodeJson(): string
    {
        $encoded = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $encoded;
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function fromRequest(array $post): self
    {
        return new self(
            scale: self::normalizeScale($post['scale'] ?? 1),
            offsetX: (float)($post['offsetX'] ?? 0),
            offsetY: (float)($post['offsetY'] ?? 0),
        );
    }

    private static function normalizeScale(mixed $value): float
    {
        $scale = (float)$value;
        if ($scale < 0.25) {
            return 0.25;
        }
        if ($scale > 4.0) {
            return 4.0;
        }

        return $scale;
    }
}
