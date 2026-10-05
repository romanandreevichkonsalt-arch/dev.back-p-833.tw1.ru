<?php

namespace app\services\media;

final class ListingTileRenderer
{
    public function __construct(
        private readonly ListingTileConfig $config = new ListingTileConfig(),
    ) {
    }

    /**
     * @throws \RuntimeException
     */
    public function renderFile(string $sourcePath, ListingFrameData $frame, string $targetPath): void
    {
        $canvas = $this->renderCanvas($sourcePath, $frame, $this->config->width, $this->config->height);
        $this->saveCanvas($canvas, $targetPath, $this->config->webpQuality);
        imagedestroy($canvas);
    }

    /**
     * @throws \RuntimeException
     */
    public function renderCanvas(
        string $sourcePath,
        ListingFrameData $frame,
        int $targetWidth,
        int $targetHeight,
    ): \GdImage {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Исходный файл не найден.');
        }

        $source = $this->loadImage($sourcePath);
        if ($source === null) {
            throw new \RuntimeException('Не удалось прочитать изображение.');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);
            throw new \RuntimeException('Некорректный размер исходника.');
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('Не удалось создать холст.');
        }

        $background = $this->allocateBackground($canvas, $this->config->backgroundHex);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);

        $coverScale = max($targetWidth / $srcW, $targetHeight / $srcH) * $frame->scale;
        $drawW = (int)round($srcW * $coverScale);
        $drawH = (int)round($srcH * $coverScale);
        $dstX = (int)round($targetWidth / 2 + $frame->offsetX - $drawW / 2);
        $dstY = (int)round($targetHeight / 2 + $frame->offsetY - $drawH / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);
        imagedestroy($source);

        return $canvas;
    }

    private function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    private function allocateBackground(\GdImage $canvas, string $hex): int
    {
        $rgb = $this->parseHexColor($hex);
        $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
        if ($color === false) {
            throw new \RuntimeException('Не удалось выделить цвет фона.');
        }

        return $color;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function parseHexColor(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [252, 251, 242];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function saveCanvas(\GdImage $canvas, string $targetPath, int $quality): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать каталог для файла.');
        }

        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $saved = match ($extension) {
            'webp' => function_exists('imagewebp') && imagewebp($canvas, $targetPath, $quality),
            'jpg', 'jpeg' => imagejpeg($canvas, $targetPath, min(95, max(70, $quality))),
            'png' => imagepng($canvas, $targetPath),
            default => function_exists('imagewebp') && imagewebp($canvas, $targetPath . '.webp', $quality),
        };

        if (!$saved || !is_file($targetPath)) {
            throw new \RuntimeException('Не удалось сохранить файл плитки.');
        }
    }
}
