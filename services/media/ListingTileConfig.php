<?php

namespace app\services\media;

use app\models\CatalogListingTileSettings;

final class ListingTileConfig
{
    public function __construct(
        public readonly int $width = 458,
        public readonly int $height = 347,
        public readonly string $backgroundHex = '#fcfbf2',
        public readonly int $floorGuideFromBottom = 75,
        public readonly int $miniMaxWidth = 200,
        public readonly int $webpQuality = 90,
    ) {
    }

    public static function fromParams(?array $config = null): self
    {
        $config = $config ?? \Yii::$app->params['catalogListingTile'] ?? [];

        $width = (int)($config['width'] ?? 458);
        $height = (int)($config['height'] ?? 347);
        $paramsGuide = (int)($config['floorGuideFromBottom'] ?? 75);
        $floorGuide = $paramsGuide;
        try {
            $floorGuide = (int)CatalogListingTileSettings::getSingleton()->floor_guide_from_bottom;
        } catch (\Throwable) {
            // Таблица ещё не создана — используем значение из params.
        }

        return new self(
            width: $width,
            height: $height,
            backgroundHex: (string)($config['background'] ?? '#fcfbf2'),
            floorGuideFromBottom: max(0, min(max(0, $height - 1), $floorGuide)),
            miniMaxWidth: (int)($config['miniMaxWidth'] ?? (\Yii::$app->params['mediaImageVariants']['miniMaxWidth'] ?? 200)),
            webpQuality: (int)($config['webpQuality'] ?? (\Yii::$app->params['mediaImageVariants']['webpQuality'] ?? 90)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorPayload(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'background' => $this->backgroundHex,
            'floorGuideFromBottom' => $this->floorGuideFromBottom,
            'aspectRatio' => '132 / 100',
        ];
    }

    public function miniHeightForWidth(int $width): int
    {
        if ($this->width <= 0) {
            return $this->height;
        }

        return (int)max(1, round($this->height * ($width / $this->width)));
    }
}
