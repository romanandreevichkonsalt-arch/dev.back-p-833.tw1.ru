<?php

namespace app\services\import\fabric;

use app\helpers\SlugHelper;
use app\models\CatalogColor;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogImportRun;
use app\models\CatalogPriceCategory;
use Yii;

class FabricRegistryImporter
{
    private const PRICE_LINE_A = 'a';
    private const PRICE_LINE_LINE1 = 'line1';

    public function __construct(
        private readonly FabricRegistrySpreadsheetReader $spreadsheetReader = new FabricRegistrySpreadsheetReader(),
        private readonly MediaImportService $mediaImportService = new MediaImportService(),
    ) {
    }

    public function import(
        string $filePath,
        FabricRegistryImportOptions $options,
        ?CatalogImportRun $run = null,
        int $startIndex = 0,
        ?FabricRegistryImportResult $accumulatedResult = null,
    ): FabricRegistryImportResult {
        $result = $accumulatedResult ?? new FabricRegistryImportResult();
        $importRun = $run;

        if ($importRun === null && $options->shouldWrite() && !$options->asyncMode) {
            $importRun = new CatalogImportRun([
                'type' => CatalogImportRun::TYPE_FABRIC,
                'user_id' => $options->userId,
                'filename' => $options->filename !== '' ? $options->filename : basename($filePath),
                'sheet' => FabricRegistrySpreadsheetReader::SHEET_FABRICS,
                'status' => CatalogImportRun::STATUS_PROCESSING,
                'phase' => CatalogImportRun::PHASE_PARSING,
                'created_at' => date('Y-m-d H:i:s'),
                'started_at' => date('Y-m-d H:i:s'),
            ]);
            $importRun->save(false);
            $result->importRun = $importRun;
        } elseif ($importRun !== null) {
            $result->importRun = $importRun;
            $options->importRunId = (int)$importRun->id;
        }

        $useTransaction = $options->shouldWrite() && !$options->asyncMode;
        $transaction = $useTransaction ? Yii::$app->db->beginTransaction() : null;

        try {
            if ($importRun !== null && $options->asyncMode) {
                $importRun->markProgress(
                    $startIndex,
                    (int)$importRun->total_rows,
                    CatalogImportRun::PHASE_PARSING,
                    'Чтение файла…'
                );
            }

            $rows = $this->spreadsheetReader->read($filePath);
            $totalRows = count($rows);
            $result->stats['rows_total'] = $totalRows;

            if ($importRun !== null) {
                $importRun->total_rows = $totalRows;
                $importRun->save(false, ['total_rows']);
            }

            if ($startIndex > 0) {
                $rows = array_slice($rows, $startIndex);
            }

            foreach ($rows as $offset => $row) {
                if ($result->aborted) {
                    break;
                }

                $absoluteIndex = $startIndex + $offset;
                $rowResult = $this->importRow($row, $options, $importRun?->id);
                $result->addRow($rowResult);
                $this->updateStats($result, $rowResult);

                if ($rowResult->action === FabricRegistryImportRowResult::ACTION_CONFLICT) {
                    $result->pendingConflict = $rowResult->conflict;
                    $conflictAction = $options->resolveConflictAction();

                    if ($conflictAction === FabricRegistryImportOptions::CONFLICT_ABORT) {
                        $result->aborted = true;
                        $result->success = false;

                        if ($options->asyncMode && $importRun !== null) {
                            $importRun->resume_row_index = $absoluteIndex;
                            $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_AWAITING_CONFLICT);
                        }
                        break;
                    }

                    if ($conflictAction === FabricRegistryImportOptions::CONFLICT_SKIP) {
                        $result->stats['links_conflict'] = max(0, $result->stats['links_conflict'] - 1);
                        $rowResult->action = FabricRegistryImportRowResult::ACTION_SKIP;
                        $rowResult->addMessage('Пара коллекция-цвет уже есть — пропущена.');
                        $this->updateStats($result, $rowResult);
                        $this->clearConflictResolutionOnce($options, $importRun);
                        continue;
                    }

                    if ($conflictAction === FabricRegistryImportOptions::CONFLICT_UPDATE) {
                        $updated = $this->updateExistingLink($row, $options, $importRun?->id, $rowResult);
                        if ($updated) {
                            $rowResult->action = FabricRegistryImportRowResult::ACTION_UPDATE;
                            $result->stats['links_updated']++;
                            $result->stats['links_conflict'] = max(0, $result->stats['links_conflict'] - 1);
                        }
                        $this->clearConflictResolutionOnce($options, $importRun);
                    }
                }

                if ($importRun !== null && $options->asyncMode) {
                    $processed = $absoluteIndex + 1;
                    if ($processed % 5 === 0 || $processed === $totalRows) {
                        $importRun->markProgress(
                            $processed,
                            $totalRows,
                            CatalogImportRun::PHASE_PROCESSING,
                            sprintf('Обработано %d строк из %d', $processed, $totalRows)
                        );
                        $this->persistRunStats($importRun, $result);
                    }
                }
            }

            if ($transaction !== null) {
                if ($result->aborted && !$options->asyncMode) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
            }

            if ($importRun !== null && !($result->aborted && $options->asyncMode)) {
                $finalStatus = $result->aborted
                    ? CatalogImportRun::STATUS_ABORTED
                    : ($options->dryRun ? 'dry_run' : CatalogImportRun::STATUS_COMPLETED);
                $this->persistRunState($importRun, $result, $finalStatus);
            }
        } catch (\Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            $result->success = false;
            $result->aborted = true;
            $result->stats['errors']++;

            if ($importRun !== null) {
                $importRun->assignErrorMessage($exception->getMessage());
                $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_FAILED);
            }

            throw $exception;
        }

        return $result;
    }

    private function clearConflictResolutionOnce(
        FabricRegistryImportOptions $options,
        ?CatalogImportRun $importRun,
    ): void {
        if (!$options->asyncMode) {
            return;
        }

        $options->clearConflictResolutionOnce();
        if ($importRun === null) {
            return;
        }

        $runOptions = $importRun->getOptions();
        unset($runOptions['conflict_resolution_once']);
        $importRun->setOptions($runOptions);
        $importRun->save(false, ['options_json']);
    }

    private function persistRunStats(CatalogImportRun $importRun, FabricRegistryImportResult $result): void
    {
        $importRun->setStats($result->toStatsPayload());
        $importRun->save(false, ['stats_json']);
    }

    private function persistRunState(
        CatalogImportRun $importRun,
        FabricRegistryImportResult $result,
        string $status
    ): void {
        $importRun->status = $status;
        $importRun->phase = $status === CatalogImportRun::STATUS_COMPLETED
            ? CatalogImportRun::PHASE_DONE
            : $importRun->phase;
        if ($importRun->isTerminal() || $status === CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            $importRun->finished_at = $status === CatalogImportRun::STATUS_AWAITING_CONFLICT
                ? null
                : date('Y-m-d H:i:s');
        }
        if ($status === CatalogImportRun::STATUS_COMPLETED) {
            $importRun->processed_rows = (int)$importRun->total_rows;
            $importRun->phase_message = sprintf(
                'Обработано %d строк из %d',
                (int)$importRun->processed_rows,
                (int)$importRun->total_rows
            );
        }
        $importRun->setStats($result->toStatsPayload());
        $importRun->save(false);
    }

    private function importRow(
        FabricRegistryRowDto $row,
        FabricRegistryImportOptions $options,
        ?int $importRunId
    ): FabricRegistryImportRowResult {
        $rowResult = new FabricRegistryImportRowResult();
        $rowResult->rowNumber = $row->rowNumber;
        $rowResult->collectionName = $row->collectionName;
        $rowResult->designCode = $row->colorName;
        $rowResult->colorLabel = $row->colorLabel;

        if ($row->isSkippable()) {
            $rowResult->action = FabricRegistryImportRowResult::ACTION_SKIP;
            $rowResult->addMessage('Строка пропущена: пустые обязательные поля или шаблон.');

            return $rowResult;
        }

        if ($row->colorName === '') {
            $rowResult->action = FabricRegistryImportRowResult::ACTION_SKIP;
            $rowResult->addWarning('Не указано название цвета в коллекции.');

            return $rowResult;
        }

        $collectionSlug = SlugHelper::slugify($row->collectionName);
        $existingCollection = CatalogFabricCollection::find()->where(['slug' => $collectionSlug])->one();
        $existingLink = null;

        if ($existingCollection !== null) {
            $existingLink = CatalogFabricColor::find()
                ->where([
                    'fabric_collection_id' => $existingCollection->id,
                    'design_code' => $row->colorName,
                ])
                ->one();
        }

        if ($existingLink !== null && !$options->updateExisting && $options->resolveConflictAction() !== FabricRegistryImportOptions::CONFLICT_UPDATE) {
            $rowResult->action = FabricRegistryImportRowResult::ACTION_CONFLICT;
            $rowResult->conflict = [
                'row_number' => $row->rowNumber,
                'collection' => $row->collectionName,
                'design_code' => $row->colorName,
                'existing' => $this->linkSnapshot($existingLink),
                'incoming' => $row->fingerprint(),
            ];
            $rowResult->addWarning('Найден дубликат: коллекция + название цвета уже существуют.');

            return $rowResult;
        }

        if (!$options->shouldWrite()) {
            $rowResult->action = $existingLink !== null
                ? FabricRegistryImportRowResult::ACTION_UPDATE
                : FabricRegistryImportRowResult::ACTION_CREATE;
            $rowResult->addMessage('Dry-run: изменения не сохранены.');
            if ($options->importMedia) {
                $this->previewMedia($row, $rowResult);
            }

            return $rowResult;
        }

        $collection = $this->upsertCollection($row, $existingCollection, $options, $rowResult);
        $rowResult->collectionId = (int)$collection->id;

        $colorId = $this->resolveColorId($row, $options, $rowResult);
        $link = $existingLink ?? new CatalogFabricColor();
        $isNewLink = $link->isNewRecord;

        $link->fabric_collection_id = (int)$collection->id;
        $link->design_code = $row->colorName;
        $link->color_id = $colorId;
        $link->import_comment = $row->importComment;
        $link->source_photo_url = $row->textureUrl;
        $link->is_recommended_fabric = $row->isRecommendedFabric;
        $link->position_number = $row->positionNumber;

        if (!$link->save()) {
            $rowResult->action = FabricRegistryImportRowResult::ACTION_ERROR;
            $rowResult->addWarning('Ошибка сохранения цвета: ' . implode(', ', $link->getFirstErrors()));

            return $rowResult;
        }

        $rowResult->linkId = (int)$link->id;
        $rowResult->action = $isNewLink
            ? FabricRegistryImportRowResult::ACTION_CREATE
            : FabricRegistryImportRowResult::ACTION_UPDATE;

        $this->importMediaForLink($row, $link, $collectionSlug, $importRunId, $rowResult, $options);
        $link->save(false);

        return $rowResult;
    }

    private function updateExistingLink(
        FabricRegistryRowDto $row,
        FabricRegistryImportOptions $options,
        ?int $importRunId,
        FabricRegistryImportRowResult $rowResult
    ): bool {
        $collectionSlug = SlugHelper::slugify($row->collectionName);
        $existingCollection = CatalogFabricCollection::find()->where(['slug' => $collectionSlug])->one();
        if ($existingCollection === null) {
            return false;
        }

        if ($options->shouldWrite()) {
            $this->upsertCollection($row, $existingCollection, $options, $rowResult);
        }

        $link = CatalogFabricColor::find()
            ->where([
                'fabric_collection_id' => $existingCollection->id,
                'design_code' => $row->colorName,
            ])
            ->one();
        if ($link === null) {
            return false;
        }

        if (!$options->shouldWrite()) {
            return true;
        }

        $colorId = $this->resolveColorId($row, $options, $rowResult);
        $link->color_id = $colorId;
        $link->import_comment = $row->importComment;
        $link->source_photo_url = $row->textureUrl;
        $link->is_recommended_fabric = $row->isRecommendedFabric;
        $link->position_number = $row->positionNumber;

        if (!$link->save()) {
            $rowResult->addWarning('Ошибка обновления цвета: ' . implode(', ', $link->getFirstErrors()));

            return false;
        }

        $rowResult->collectionId = (int)$existingCollection->id;
        $rowResult->linkId = (int)$link->id;

        $this->importMediaForLink($row, $link, $collectionSlug, $importRunId, $rowResult, $options);
        $link->save(false);

        return true;
    }

    private function upsertCollection(
        FabricRegistryRowDto $row,
        ?CatalogFabricCollection $existing,
        FabricRegistryImportOptions $options,
        FabricRegistryImportRowResult $rowResult
    ): CatalogFabricCollection {
        $collection = $existing ?? new CatalogFabricCollection();
        $isNew = $collection->isNewRecord;

        $collection->name = $row->collectionName;
        $collection->slug = SlugHelper::slugify($row->collectionName);
        $collection->material_kind = $row->materialKind;
        $this->applyCollectionFields($collection, $row);
        $collection->price_category_id = $this->resolvePriceCategoryId(
            $row->priceCategoryLabelA,
            self::PRICE_LINE_A,
            $options,
            $rowResult
        );
        $collection->price_category_line1_id = $this->resolvePriceCategoryId(
            $row->priceCategoryLabelLine1,
            self::PRICE_LINE_LINE1,
            $options,
            $rowResult
        );
        $collection->import_source = $options->importSource;
        $collection->import_row_hash = $row->rowHash();
        $collection->is_active = true;

        if (!$collection->save()) {
            throw new \RuntimeException(
                'Не удалось сохранить коллекцию «' . $row->collectionName . '»: '
                . implode(', ', $collection->getFirstErrors())
            );
        }

        if ($isNew) {
            $rowResult->addMessage('Создана коллекция «' . $row->collectionName . '».');
        } else {
            $rowResult->addMessage('Обновлена коллекция «' . $row->collectionName . '».');
        }

        return $collection;
    }

    private function applyCollectionFields(CatalogFabricCollection $collection, FabricRegistryRowDto $row): void
    {
        if ($row->texture !== null && $row->texture !== '') {
            $collection->texture = $row->texture;
        }
        if ($row->composition !== null && $row->composition !== '') {
            $collection->composition = $row->composition;
        }
        if ($row->martindale !== null) {
            $collection->martindale = $row->martindale;
        }
        if ($row->properties !== null && $row->properties !== '') {
            $collection->care_instructions = $row->properties;
        }
        if ($row->rollWidthCm !== null) {
            $collection->roll_width_cm = $row->rollWidthCm;
        }
        if ($row->densityGsm !== null) {
            $collection->density_gsm = $row->densityGsm;
        }
        if ($row->description !== null && $row->description !== '') {
            $collection->description = $row->description;
        }
    }

    private function resolvePriceCategoryId(
        ?string $label,
        string $line,
        FabricRegistryImportOptions $options,
        FabricRegistryImportRowResult $rowResult
    ): ?int {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $parsed = PriceCategoryLabelParser::parse($label);
        if ($parsed === null) {
            return null;
        }

        $number = (int)$parsed['number'];
        if ($number < 1) {
            return null;
        }

        if ($options->shouldWrite()) {
            $this->ensurePriceCategoriesUpTo($number, $rowResult);
            $category = CatalogPriceCategory::find()->where(['number' => $number])->one();
            if ($category !== null) {
                $this->applyPriceCategoryFromParsedLabel($category, $parsed, $line, $rowResult);
            }
        }

        $category = CatalogPriceCategory::find()->where(['number' => $number])->one();

        return $category !== null ? (int)$category->id : null;
    }

    /**
     * @param array{number:int,label:string,price_min:?int,price_max:?int} $parsed
     */
    private function applyPriceCategoryFromParsedLabel(
        CatalogPriceCategory $category,
        array $parsed,
        string $line,
        FabricRegistryImportRowResult $rowResult
    ): void {
        $changed = false;

        if ($line === self::PRICE_LINE_A) {
            if ($parsed['price_min'] !== null) {
                $category->price_min = $parsed['price_min'];
                $changed = true;
            }
            if ($parsed['price_max'] !== null) {
                $category->price_max = $parsed['price_max'];
                $changed = true;
            }
            if ($parsed['price_min'] !== null || $parsed['price_max'] !== null) {
                $category->label = PriceCategoryLabelParser::format(
                    (int)$category->number,
                    $category->price_min !== null ? (int)$category->price_min : null,
                    $category->price_max !== null ? (int)$category->price_max : null,
                );
                $changed = true;
            }
        } elseif ($line === self::PRICE_LINE_LINE1) {
            if ($parsed['price_min'] !== null) {
                $category->price_min_line1 = $parsed['price_min'];
                $changed = true;
            }
            if ($parsed['price_max'] !== null) {
                $category->price_max_line1 = $parsed['price_max'];
                $changed = true;
            }
            if ($parsed['price_min'] !== null || $parsed['price_max'] !== null) {
                $category->label_line1 = PriceCategoryLabelParser::format(
                    (int)$category->number,
                    $category->price_min_line1 !== null ? (int)$category->price_min_line1 : null,
                    $category->price_max_line1 !== null ? (int)$category->price_max_line1 : null,
                );
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        if (!$category->save()) {
            throw new \RuntimeException(
                'Не удалось обновить ценовую категорию #' . $category->number . ': '
                . implode(', ', $category->getFirstErrors())
            );
        }

        $lineLabel = $line === self::PRICE_LINE_A ? 'А+' : 'Линия 1';
        $rowResult->addMessage(
            'Обновлена ценовая категория #' . $category->number . ' (' . $lineLabel . ').'
        );
    }

    private function ensurePriceCategoriesUpTo(int $number, FabricRegistryImportRowResult $rowResult): void
    {
        for ($i = 1; $i <= $number; $i++) {
            $category = CatalogPriceCategory::find()->where(['number' => $i])->one();
            if ($category !== null) {
                continue;
            }

            $category = new CatalogPriceCategory([
                'number' => $i,
                'label' => 'Категория ' . $i,
                'is_active' => true,
            ]);

            if (!$category->save()) {
                throw new \RuntimeException(
                    'Не удалось создать ценовую категорию #' . $i . ': '
                    . implode(', ', $category->getFirstErrors())
                );
            }

            $rowResult->addMessage('Создана ценовая категория #' . $i . '.');
        }
    }

    private function resolveColorId(
        FabricRegistryRowDto $row,
        FabricRegistryImportOptions $options,
        FabricRegistryImportRowResult $rowResult
    ): ?int {
        if ($row->colorLabel === null || trim($row->colorLabel) === '') {
            return null;
        }

        if (!FabricColorNameValidator::isImportableColorName($row->colorLabel)) {
            $rowResult->addWarning(
                'Цвет «' . $row->colorLabel . '» не импортирован в справочник — связь сохранена без color_id.'
            );

            return null;
        }

        $label = FabricColorNameValidator::normalizeLabel($row->colorLabel);
        $slug = SlugHelper::slugify($label);

        $color = CatalogColor::find()
            ->where(['slug' => $slug])
            ->orWhere(['label' => $label])
            ->one();

        $hex = \app\helpers\CatalogFabricBaseColorPalette::resolveHex($label, $slug);

        if ($color === null) {
            if (!$options->shouldWrite()) {
                $rowResult->addMessage('Dry-run: будет создан цвет «' . $label . '».');

                return null;
            }

            $color = new CatalogColor([
                'label' => $label,
                'slug' => $slug,
                'hex_color' => $hex,
                'is_active' => true,
            ]);

            if (!$color->save()) {
                throw new \RuntimeException(
                    'Не удалось создать цвет «' . $label . '»: ' . implode(', ', $color->getFirstErrors())
                );
            }

            $rowResult->addMessage('Создан цвет «' . $label . '».');
        } elseif (($color->hex_color === null || $color->hex_color === '') && $hex !== null) {
            $color->hex_color = $hex;
            $color->save(false, ['hex_color', 'updated_at']);
        }

        return (int)$color->id;
    }

    private function importMediaForLink(
        FabricRegistryRowDto $row,
        CatalogFabricColor $link,
        string $collectionSlug,
        ?int $importRunId,
        FabricRegistryImportRowResult $rowResult,
        FabricRegistryImportOptions $options,
    ): void {
        if (!$options->shouldImportMedia()) {
            return;
        }

        $textureUrl = $row->textureUrl;
        if ($textureUrl === null || $textureUrl === '') {
            return;
        }

        $downloadUrl = CloudStorageUrlResolver::resolve($textureUrl);
        if ($downloadUrl === null) {
            $rowResult->photoSkipped = true;
            $reason = CloudStorageUrlResolver::unsupportedReason($textureUrl)
                ?? 'Ссылка не является прямым файлом изображения.';
            $rowResult->addMessage(
                'Фото не загружено: ' . $reason . ' Сохранена исходная ссылка для ручной загрузки.'
            );

            return;
        }

        $mediaSlug = FabricDesignCodeNormalizer::normalize($row->colorName);

        try {
            $media = $this->mediaImportService->importFabricTexture(
                $downloadUrl,
                $collectionSlug,
                $mediaSlug,
                $importRunId,
                $row->rowNumber
            );
            if ($media === null) {
                $rowResult->photoSkipped = true;

                return;
            }

            if ($media->kind === \app\models\MediaFile::KIND_IMAGE) {
                $link->swatch_media_id = (int)$media->id;
            } else {
                $link->pbr_media_id = (int)$media->id;
            }
            $rowResult->photoImported = true;
            $rowResult->addMessage('Файл сохранён в папку «Ткани».');
        } catch (\Throwable $exception) {
            $rowResult->photoSkipped = true;
            $rowResult->addWarning('Ошибка загрузки в медиатеку: ' . $exception->getMessage());
        }
    }

    private function previewMedia(FabricRegistryRowDto $row, FabricRegistryImportRowResult $rowResult): void
    {
        $textureUrl = $row->textureUrl ?? '';
        if ($textureUrl === '') {
            return;
        }

        $downloadUrl = CloudStorageUrlResolver::resolve($textureUrl);
        if ($downloadUrl === null) {
            $rowResult->photoSkipped = true;
            $reason = CloudStorageUrlResolver::unsupportedReason($textureUrl)
                ?? 'Ссылка на фото не распознана.';
            $rowResult->addMessage($reason . ' Потребуется ручная загрузка.');
        } else {
            $rowResult->addMessage('Будет загружен файл в папку «Ткани».');
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function linkSnapshot(CatalogFabricColor $link): array
    {
        return [
            'id' => $link->id,
            'design_code' => $link->design_code,
            'color_id' => $link->color_id,
            'import_comment' => $link->import_comment,
            'is_recommended_fabric' => (bool)$link->is_recommended_fabric,
            'position_number' => $link->position_number,
        ];
    }

    private function updateStats(FabricRegistryImportResult $result, FabricRegistryImportRowResult $rowResult): void
    {
        if ($rowResult->action === FabricRegistryImportRowResult::ACTION_SKIP) {
            $result->stats['rows_skipped']++;
        }
        if ($rowResult->action === FabricRegistryImportRowResult::ACTION_CREATE) {
            $result->stats['links_created']++;
        }
        if ($rowResult->action === FabricRegistryImportRowResult::ACTION_UPDATE) {
            $result->stats['links_updated']++;
        }
        if ($rowResult->action === FabricRegistryImportRowResult::ACTION_CONFLICT) {
            $result->stats['links_conflict']++;
        }
        if ($rowResult->action === FabricRegistryImportRowResult::ACTION_ERROR) {
            $result->stats['errors']++;
        }
        if ($rowResult->photoImported) {
            $result->stats['photo_imported']++;
        }
        if ($rowResult->photoSkipped) {
            $result->stats['photo_skipped']++;
        }

        foreach ($rowResult->messages as $message) {
            if (str_contains($message, 'Создан цвет')) {
                $result->stats['colors_created']++;
            }
            if (str_contains($message, 'Создана коллекция')) {
                $result->stats['collections_created']++;
            }
            if (str_contains($message, 'Обновлена коллекция')) {
                $result->stats['collections_updated']++;
            }
            if (str_contains($message, 'Создана ценовая категория')) {
                $result->stats['price_categories_created']++;
            }
        }
    }
}
