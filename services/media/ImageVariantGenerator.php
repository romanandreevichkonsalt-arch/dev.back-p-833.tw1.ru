<?php

namespace app\services\media;

class ImageVariantGenerator
{
    private int $mediumMaxWidth;
    private int $largeMaxWidth;
    private int $miniMaxWidth;
    private int $webpQuality;
    private int $largeWebpQuality;
    private int $largeWebpQualityNoResize;

    public function __construct(?array $config = null)
    {
        $config = $config ?? \Yii::$app->params['mediaImageVariants'] ?? [];
        $this->mediumMaxWidth = (int)($config['mediumMaxWidth'] ?? 1200);
        $this->largeMaxWidth = (int)($config['largeMaxWidth'] ?? 1920);
        $this->miniMaxWidth = (int)($config['miniMaxWidth'] ?? 200);
        $this->webpQuality = (int)($config['webpQuality'] ?? 90);
        $this->largeWebpQuality = (int)($config['largeWebpQuality'] ?? 96);
        $this->largeWebpQualityNoResize = (int)($config['largeWebpQualityNoResize'] ?? 98);
    }

    /**
     * @return array{large:?string,medium:?string,mini:?string}
     */
    public function generate(string $sourcePath, string $basenameWithoutExt): array
    {
        $image = $this->loadImage($sourcePath);
        if ($image === null) {
            return ['large' => null, 'medium' => null, 'mini' => null];
        }

        $dir = dirname($sourcePath);
        $largePath = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_l.webp';
        $mediumPath = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_m.webp';
        $miniPath = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_s.webp';

        $sourceWidth = imagesx($image);
        $largeQuality = $sourceWidth <= $this->largeMaxWidth
            ? $this->largeWebpQualityNoResize
            : $this->largeWebpQuality;
        if ($sourceWidth <= $this->largeMaxWidth
            && strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'webp'
            && is_file($sourcePath)
            && copy($sourcePath, $largePath)
        ) {
            $largeOk = true;
        } else {
            $largeOk = $this->saveResizedWebp($image, $this->largeMaxWidth, $largePath, $largeQuality);
        }
        $mediumOk = $this->saveResizedWebp($image, $this->mediumMaxWidth, $mediumPath, $this->webpQuality);
        $miniOk = $this->saveResizedWebp($image, $this->miniMaxWidth, $miniPath, $this->webpQuality);

        if (!$largeOk || !$mediumOk || !$miniOk) {
            $largeJpeg = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_l.jpg';
            $mediumJpeg = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_m.jpg';
            $miniJpeg = $dir . DIRECTORY_SEPARATOR . $basenameWithoutExt . '_s.jpg';
            if (!$largeOk) {
                $largeOk = $this->saveResizedJpeg($image, $this->largeMaxWidth, $largeJpeg);
                $largePath = $largeOk ? $largeJpeg : $largePath;
            }
            if (!$mediumOk) {
                $mediumOk = $this->saveResizedJpeg($image, $this->mediumMaxWidth, $mediumJpeg);
                $mediumPath = $mediumOk ? $mediumJpeg : $mediumPath;
            }
            if (!$miniOk) {
                $miniOk = $this->saveResizedJpeg($image, $this->miniMaxWidth, $miniJpeg);
                $miniPath = $miniOk ? $miniJpeg : $miniPath;
            }
        }

        if ($image instanceof \GdImage) {
            imagedestroy($image);
        }

        return [
            'large' => $largeOk ? $largePath : null,
            'medium' => $mediumOk ? $mediumPath : null,
            'mini' => $miniOk ? $miniPath : null,
        ];
    }

    private function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path) ?: null;
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path) ?: null;
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null;
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path) ?: null;
            default:
                return null;
        }
    }

    private function saveResizedWebp(\GdImage $source, int $maxWidth, string $targetPath, int $quality): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $resized = $this->resizeToMaxWidth($source, $maxWidth);
        $this->preserveAlpha($resized, $source);
        $ok = imagewebp($resized, $targetPath, $quality);
        imagedestroy($resized);

        return $ok && is_file($targetPath);
    }

    private function saveResizedJpeg(\GdImage $source, int $maxWidth, string $targetPath): bool
    {
        $resized = $this->resizeToMaxWidth($source, $maxWidth);
        $width = imagesx($resized);
        $height = imagesy($resized);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $resized, 0, 0, 0, 0, $width, $height);
        imagedestroy($resized);
        $ok = imagejpeg($canvas, $targetPath, 90);
        imagedestroy($canvas);

        return $ok && is_file($targetPath);
    }

    private function resizeToMaxWidth(\GdImage $source, int $maxWidth): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxWidth) {
            $copy = imagecreatetruecolor($width, $height);
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopy($copy, $source, 0, 0, 0, 0, $width, $height);

            return $copy;
        }

        $newWidth = $maxWidth;
        $newHeight = (int)round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function preserveAlpha(\GdImage $target, \GdImage $source): void
    {
        imagealphablending($target, false);
        imagesavealpha($target, true);
    }
}
