<?php

namespace app\services\import\catalog;

use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\CatalogModelImage;

/**
 * TEMPORARY: разовый прогон — записать source_url из прайса на уже привязанные фото, без загрузки.
 * Удалить после миграции каталога на source_url.
 */
final class CatalogModelImportPhotoUrlBackfillService
{
    /**
     * @param list<string> $messages
     */
    public function backfillForModel(CatalogModel $model, CatalogModelImportRowDto $row, array &$messages): int
    {
        $written = 0;
        $written += $this->backfillUrlList(
            $model,
            $row->galleryPhotoUrls,
            CatalogModelImage::class,
            CatalogModelImage::PURPOSE_INTERIOR,
            'галерея',
            $messages,
        );
        $written += $this->backfillUrlList(
            $model,
            $row->dimensionPhotoUrls,
            CatalogModelDimensionImage::class,
            null,
            'габариты',
            $messages,
        );

        return $written;
    }

    /**
     * @param list<string> $urls
     * @param class-string<CatalogModelImage|CatalogModelDimensionImage> $linkClass
     * @param list<string> $messages
     */
    private function backfillUrlList(
        CatalogModel $model,
        array $urls,
        string $linkClass,
        ?string $purpose,
        string $label,
        array &$messages,
    ): int {
        if ($urls === []) {
            return 0;
        }

        $query = $linkClass::find()
            ->where(['model_id' => (int)$model->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);

        if ($purpose !== null && $linkClass === CatalogModelImage::class) {
            $query->andWhere(['purpose' => $purpose]);
        }

        /** @var list<CatalogModelImage|CatalogModelDimensionImage> $existing */
        $existing = $query->all();
        $written = 0;

        foreach ($urls as $slotIndex => $rawUrl) {
            $incomingUrl = trim($rawUrl);
            if ($incomingUrl === '') {
                continue;
            }

            $link = $existing[$slotIndex] ?? null;
            if ($link === null || (int)$link->media_file_id <= 0) {
                continue;
            }

            if (!$link->hasAttribute('source_url')) {
                continue;
            }

            $stored = trim((string)$link->source_url);
            if ($stored !== '' && $stored === mb_substr($incomingUrl, 0, 512)) {
                continue;
            }

            $link->source_url = mb_substr($incomingUrl, 0, 512);
            if ($link->save(false, ['source_url'])) {
                $written++;
            }
        }

        if ($written > 0) {
            $messages[] = sprintf('Записано ссылок (%s): %d.', $label, $written);
        }

        return $written;
    }
}
