<?php

namespace app\services\import\catalog;

use app\models\CatalogFabricCollection;
use app\models\CatalogImportRun;
use app\models\CatalogModel;
use app\services\catalog\CatalogFilterFunction;
use app\services\catalog\CatalogModelProductSyncService;
use Yii;

class CatalogModelImporter
{
    private const ROW_BATCH_SIZE = 50;

    public function __construct(
        private readonly CatalogModelImportSpreadsheetReader $reader = new CatalogModelImportSpreadsheetReader(),
        private readonly CatalogImportReferenceResolver $resolver = new CatalogImportReferenceResolver(),
        private readonly CatalogModelProductSyncService $syncService = new CatalogModelProductSyncService(),
        private readonly CatalogModelMediaImportService $mediaImport = new CatalogModelMediaImportService(),
        private readonly CatalogModelFilterFunctionApplier $filterFunctionApplier = new CatalogModelFilterFunctionApplier(),
    ) {
    }

    public function import(
        string $filePath,
        CatalogModelImportOptions $options,
        ?CatalogImportRun $run = null,
        int $startIndex = 0,
        ?CatalogModelImportResult $accumulatedResult = null,
    ): CatalogModelImportResult {
        $result = $accumulatedResult ?? new CatalogModelImportResult();
        $importRun = $run;

        if ($importRun !== null && $options->asyncMode) {
            $importRun->markProgress(
                $startIndex,
                (int)$importRun->total_rows,
                CatalogImportRun::PHASE_PARSING,
                'Чтение файла…'
            );
        }

        $allRows = $this->reader->read($filePath, 0);
        $totalRows = count($allRows);

        if ($importRun !== null) {
            $importRun->total_rows = $totalRows;
            $importRun->save(false, ['total_rows']);
        }

        if ($startIndex === 0) {
            $maxCategory = 1;
            foreach ($allRows as $row) {
                foreach (array_keys($row->pricesByCategoryNumber) as $number) {
                    $maxCategory = max($maxCategory, (int)$number);
                }
            }

            if ($options->shouldWrite()) {
                $this->resolver->ensurePriceCategoriesUpTo($maxCategory);
            }
        }

        $rows = $startIndex > 0 ? array_slice($allRows, $startIndex) : $allRows;
        $modelLookup = CatalogModelImportModelLookup::warm($allRows, $this->resolver, $options);

        if ($importRun !== null && $options->asyncMode) {
            $importRun->markProgress(
                $startIndex,
                $totalRows,
                CatalogImportRun::PHASE_PROCESSING,
                $totalRows > 0
                    ? sprintf('Разобрано %d строк, обработка…', $totalRows)
                    : 'Обработка строк…',
                autoComplete: false,
            );
        }

        /** @var array<int, bool> modelId => onlyAddMissingFabricProducts */
        $deferredProductSync = [];

        $batchRowCount = 0;
        $transaction = null;
        $useRowBatches = $options->shouldWrite() && !$options->dryRun && !$options->asyncMode;
        $useRowTransactions = $options->shouldWrite() && !$options->dryRun && $options->asyncMode;

        foreach ($rows as $offset => $row) {
            if ($result->aborted) {
                if ($transaction !== null) {
                    $transaction->rollBack();
                    $transaction = null;
                }
                break;
            }

            $absoluteIndex = $startIndex + $offset;

            if ($importRun !== null && $options->asyncMode) {
                $importRun->markProgress(
                    $absoluteIndex,
                    $totalRows,
                    CatalogImportRun::PHASE_PROCESSING,
                    sprintf('Строка %d из %d…', $absoluteIndex + 1, $totalRows),
                    autoComplete: false,
                );
            }

            if ($useRowBatches && $transaction === null) {
                $transaction = Yii::$app->db->beginTransaction();
            } elseif ($useRowTransactions) {
                $transaction = Yii::$app->db->beginTransaction();
            }

            $rowResult = new CatalogModelImportRowResult();
            $rowResult->rowNumber = $row->rowNumber;
            $rowResult->collectionName = $row->collectionName;
            $rowResult->modelLabel = $row->displayLabel;

            try {
                $this->importRow($row, $options, $result, $rowResult, $importRun, $modelLookup, $deferredProductSync);
            } catch (\Throwable $exception) {
                if ($useRowTransactions && $transaction !== null) {
                    $transaction->rollBack();
                    $transaction = null;
                }
                $rowResult->action = 'error';
                $rowResult->messages[] = $exception->getMessage();
                $result->stats['errors']++;
                $result->success = false;
            }

            $result->rows[] = $rowResult;

            if ($result->aborted && $rowResult->action === 'conflict') {
                if ($transaction !== null) {
                    $transaction->rollBack();
                    $transaction = null;
                }
                if ($options->asyncMode && $importRun !== null) {
                    $importRun->resume_row_index = $absoluteIndex;
                    $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_AWAITING_CONFLICT);
                }
                break;
            }

            if ($useRowTransactions && $transaction !== null) {
                $transaction->commit();
                $transaction = null;
            } elseif ($useRowBatches) {
                $batchRowCount++;
                if ($batchRowCount >= self::ROW_BATCH_SIZE) {
                    $transaction?->commit();
                    $transaction = null;
                    $batchRowCount = 0;
                }
            }

            if ($importRun !== null && $options->asyncMode) {
                $processed = $absoluteIndex + 1;
                $importRun->markProgress(
                    $processed,
                    $totalRows,
                    CatalogImportRun::PHASE_PROCESSING,
                    sprintf('Обработано %d строк из %d', $processed, $totalRows),
                    autoComplete: false,
                );
                if ($processed % 5 === 0 || $processed === $totalRows) {
                    $this->persistRunStats($importRun, $result);
                }
            }
        }

        if ($transaction !== null) {
            $transaction->commit();
        }

        $this->runDeferredProductSync($deferredProductSync, $options, $importRun, $result);

        if ($importRun !== null && $options->asyncMode && !($result->aborted && $importRun->status === CatalogImportRun::STATUS_AWAITING_CONFLICT)) {
            $completion = new CatalogModelImportRunCompletion();
            if ($result->aborted) {
                $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_ABORTED);
            } elseif ($options->dryRun) {
                $this->persistRunState($importRun, $result, 'dry_run');
            } elseif ($options->shouldImportMedia() && $completion->pendingModelMediaCount((int)$importRun->id) > 0) {
                $importRun->phase = CatalogImportRun::PHASE_MEDIA;
                $importRun->phase_message = 'Строки обработаны, загрузка фото…';
                $importRun->status = CatalogImportRun::STATUS_PROCESSING;
                $importRun->finished_at = null;
                if ($importRun->supportsMediaProgress()) {
                    $importRun->media_total = $completion->pendingModelMediaCount((int)$importRun->id);
                    $importRun->media_processed_rows = 0;
                }
                $importRun->processed_rows = $totalRows;
                $this->persistRunStats($importRun, $result);
                $importRun->save(false);
            } elseif ($options->asyncMode) {
                $importRun->processed_rows = $totalRows;
                $importRun->phase = CatalogImportRun::PHASE_PROCESSING;
                $importRun->phase_message = sprintf('Обработано %d строк из %d', $totalRows, $totalRows);
                $importRun->status = CatalogImportRun::STATUS_PROCESSING;
                $importRun->finished_at = null;
                $this->persistRunStats($importRun, $result);
                $importRun->save(false);
            } else {
                $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_COMPLETED);
            }
        }

        return $result;
    }

    private function persistRunStats(CatalogImportRun $importRun, CatalogModelImportResult $result): void
    {
        $payload = $result->toArray();
        unset($payload['rows']);
        $importRun->setStats($payload);
        $importRun->save(false, ['stats_json']);
    }

    private function persistRunState(
        CatalogImportRun $importRun,
        CatalogModelImportResult $result,
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
        $importRun->setStats($result->toArray());
        $importRun->save(false);
    }

    private function clearConflictResolutionOnce(
        CatalogModelImportOptions $options,
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

    private function importRow(
        CatalogModelImportRowDto $row,
        CatalogModelImportOptions $options,
        CatalogModelImportResult $result,
        CatalogModelImportRowResult $rowResult,
        ?CatalogImportRun $importRun = null,
        ?CatalogModelImportModelLookup $modelLookup = null,
        array &$deferredProductSync = [],
    ): void {
        $modelLookup ??= new CatalogModelImportModelLookup();
        $direction = $options->dryRun
            ? null
            : $this->resolver->resolveDirection($row->directionLabel);

        $collection = $options->dryRun || $direction === null
            ? null
            : $this->resolver->resolveCollection((int)$direction->id, $row->collectionName);

        $category = null;
        $subcategory = null;
        if (!$options->dryRun) {
            $category = $this->resolver->resolveCategory($row->categoryLabel);
            $subcategory = $this->resolver->resolveSubcategory($category, $row->subcategoryLabel);
        }

        $existing = $collection !== null && $subcategory !== null
            ? $modelLookup->find((int)$collection->id, (int)$subcategory->id)
            : null;

        if ($existing !== null && !$options->updateExisting && $options->resolveConflictAction() !== CatalogModelImportOptions::CONFLICT_UPDATE) {
            $conflictAction = $options->resolveConflictAction();

            if ($conflictAction === CatalogModelImportOptions::CONFLICT_SKIP) {
                $rowResult->action = 'skipped';
                $rowResult->messages[] = 'Модель уже существует — пропущена.';
                $result->stats['models_skipped']++;
                $this->clearConflictResolutionOnce($options, $importRun);

                return;
            }

            $result->aborted = true;
            $result->stats['models_conflict']++;
            $result->conflicts[] = [
                'row_number' => $row->rowNumber,
                'collection_name' => $row->collectionName,
                'model_label' => $row->displayLabel,
                'model_id' => (int)$existing->id,
            ];
            $result->pendingConflict ??= $result->conflicts[count($result->conflicts) - 1];
            $rowResult->action = 'conflict';
            $rowResult->messages[] = 'Модель уже существует — требуется подтверждение.';

            return;
        }

        if ($options->backfillPhotoSourceUrlsOnly) {
            $this->importRowBackfillPhotoUrlsOnly($row, $options, $result, $rowResult, $existing);

            return;
        }

        if ($options->skipIfNoFabric && $existing === null && !$this->rowHasFabricBindingSource($row)) {
            $rowResult->action = 'skipped';
            $rowResult->messages[] = $row->fabricCollectionNames === []
                ? 'Строка пропущена: в каталоге нет активных коллекций тканей.'
                : 'Строка пропущена: указанные коллекции тканей не найдены в каталоге.';
            $result->stats['models_skipped']++;

            return;
        }

        if ($options->dryRun) {
            $rowResult->action = $existing !== null ? 'would_update' : 'would_create';
            $rowResult->messages[] = sprintf(
                '%s / %s (%s) — %d цен.',
                $row->collectionName,
                $row->displayLabel,
                $row->directionLabel,
                count($row->pricesByCategoryNumber)
            );
            if ($existing !== null) {
                $result->stats['models_updated']++;
            } else {
                $result->stats['models_created']++;
            }

            return;
        }

        $category ??= $this->resolver->resolveCategory($row->categoryLabel);
        $subcategory ??= $this->resolver->resolveSubcategory($category, $row->subcategoryLabel);

        $model = $existing ?? new CatalogModel();
        $isNew = $model->isNewRecord;
        $model->collection_id = (int)$collection->id;
        $model->category_id = (int)$category->id;
        $model->subcategory_id = (int)$subcategory->id;
        $model->title = '';
        $model->is_active = true;
        $this->applyImportedAttributes($model, $row, (string)$category->slug);
        if ($isNew) {
            $model->sort_order = $row->rowNumber;
        }

        if (!$model->save()) {
            throw new \RuntimeException(implode(' ', $model->getFirstErrors()));
        }

        $modelLookup->remember($model);

        $pricesByCategoryId = [];
        foreach ($row->pricesByCategoryNumber as $number => $amount) {
            $categoryId = $this->resolver->resolvePriceCategoryId((int)$number);
            if ($categoryId === null) {
                continue;
            }
            $pricesByCategoryId[$categoryId] = CatalogPriceDisplayFormatter::formatRubles((int)$amount);
        }

        $model->syncPrices($pricesByCategoryId);
        $model->syncPriceCategoryLinks(array_keys($pricesByCategoryId));
        $fabricsChanged = $this->syncFabricCollections($model, $row, $rowResult);
        foreach ($this->mediaImport->applyToModel($model, $row, $options, $result) as $mediaWarning) {
            $rowResult->messages[] = $mediaWarning;
        }
        if (
            $model->isAttributeChanged('fitting_room_url')
            || $model->isAttributeChanged('tech_photos_folder_url')
            || $model->isAttributeChanged('video_id')
            || $model->isAttributeChanged('polygons_3d')
            || $model->isAttributeChanged('file_3d_url')
            || $model->isAttributeChanged('file_3d_id')
        ) {
            $model->save(false);
        }
        if ($fabricsChanged || $isNew) {
            $deferredProductSync[(int)$model->id] = !$isNew;
        }

        $rowResult->action = $isNew ? 'created' : 'updated';
        $rowResult->messages[] = ($isNew ? 'Создана' : 'Обновлена') . ' модель с ' . count($pricesByCategoryId) . ' ценами.';
        if ($isNew) {
            $result->stats['models_created']++;
        } else {
            $result->stats['models_updated']++;
        }

        $this->clearConflictResolutionOnce($options, $importRun);
    }

    /**
     * @param array<int, bool> $deferredProductSync modelId => onlyAddMissingFabricProducts
     */
    private function runDeferredProductSync(
        array $deferredProductSync,
        CatalogModelImportOptions $options,
        ?CatalogImportRun $importRun,
        CatalogModelImportResult $result,
    ): void {
        if ($deferredProductSync === [] || !$options->shouldWrite() || $options->dryRun) {
            return;
        }

        $syncTotal = count($deferredProductSync);
        $syncIndex = 0;

        foreach ($deferredProductSync as $modelId => $onlyAddMissing) {
            $modelId = (int)$modelId;
            $model = CatalogModel::findOne($modelId);
            if ($model === null) {
                continue;
            }

            try {
                $this->syncService->syncForModel($model, onlyAddMissingFabricProducts: $onlyAddMissing);
            } catch (\Throwable $exception) {
                $result->stats['errors'] = (int)($result->stats['errors'] ?? 0) + 1;
                $result->success = false;
                Yii::error(
                    sprintf('SKU sync failed for model #%d: %s', $modelId, $exception->getMessage()),
                    __METHOD__
                );
            }

            $syncIndex++;
            if ($importRun !== null && $options->asyncMode) {
                $importRun->phase_message = sprintf(
                    'Синхронизация SKU: %d из %d моделей…',
                    $syncIndex,
                    $syncTotal
                );
                $importRun->save(false, ['phase_message']);
            }
        }
    }

    private function importRowBackfillPhotoUrlsOnly(
        CatalogModelImportRowDto $row,
        CatalogModelImportOptions $options,
        CatalogModelImportResult $result,
        CatalogModelImportRowResult $rowResult,
        ?CatalogModel $existing,
    ): void {
        $rowResult->rowNumber = $row->rowNumber;
        $rowResult->collectionName = $row->collectionName;
        $rowResult->modelLabel = $row->displayLabel;

        if ($options->dryRun) {
            $rowResult->action = 'skipped';
            $rowResult->messages[] = 'Dry-run: backfill source_url не выполняется.';

            return;
        }

        if ($existing === null) {
            $rowResult->action = 'skipped';
            $rowResult->messages[] = 'Модель не найдена — ссылки не записаны.';
            $result->stats['models_skipped']++;

            return;
        }

        $messages = [];
        $written = (new CatalogModelImportPhotoUrlBackfillService())->backfillForModel($existing, $row, $messages);
        foreach ($messages as $message) {
            $rowResult->messages[] = $message;
        }

        if ($written > 0) {
            $rowResult->action = 'updated';
            $rowResult->messages[] = sprintf('Всего записано ссылок на фото: %d.', $written);
            $result->stats['photo_urls_backfilled'] = (int)($result->stats['photo_urls_backfilled'] ?? 0) + $written;
            $result->stats['models_updated']++;
        } else {
            $rowResult->action = 'skipped';
            $rowResult->messages[] = 'Нет слотов с фото для записи ссылки (или URL уже совпадают).';
            $result->stats['models_skipped']++;
        }
    }

    private function rowHasFabricBindingSource(CatalogModelImportRowDto $row): bool
    {
        if ($row->fabricCollectionNames === []) {
            return $this->activeFabricCollectionIds() !== [];
        }

        return $this->hasResolvableFabric($row);
    }

    private function hasResolvableFabric(CatalogModelImportRowDto $row): bool
    {
        if ($row->fabricCollectionNames === []) {
            return false;
        }

        foreach ($row->fabricCollectionNames as $fabricName) {
            if ($this->resolver->resolveFabricCollectionByName($fabricName) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function activeFabricCollectionIds(): array
    {
        return array_map(
            'intval',
            CatalogFabricCollection::find()
                ->select('id')
                ->where(['is_active' => true])
                ->column()
        );
    }

    /**
     * @return bool true если добавлены новые связи с тканями
     */
    private function syncFabricCollections(
        CatalogModel $model,
        CatalogModelImportRowDto $row,
        CatalogModelImportRowResult $rowResult
    ): bool {
        if ($row->fabricCollectionNames === []) {
            $added = $model->mergeFabricCollectionLinks($this->activeFabricCollectionIds());
            if ($added > 0) {
                $rowResult->messages[] = sprintf(
                    'Добавлено %d связей со всеми активными коллекциями тканей.',
                    $added
                );
            }

            return $added > 0;
        }

        $fabricIds = [];
        foreach ($row->fabricCollectionNames as $fabricName) {
            $fabric = $this->resolver->resolveFabricCollectionByName($fabricName);
            if ($fabric === null) {
                $rowResult->messages[] = 'Коллекция тканей «' . $fabricName . '» не найдена.';

                continue;
            }
            $fabricIds[] = (int)$fabric->id;
        }

        if ($fabricIds === []) {
            return false;
        }

        $added = $model->mergeFabricCollectionLinks($fabricIds);
        if ($added > 0) {
            $rowResult->messages[] = sprintf('Добавлено %d связей с коллекциями тканей из файла.', $added);
        }

        return $added > 0;
    }

    private function applyImportedAttributes(CatalogModel $model, CatalogModelImportRowDto $row, string $categorySlug): void
    {
        $map = [
            'subtitle' => $row->subtitle,
            'description' => $row->description,
            'overall_size' => $row->overallSize,
            'seat_depth' => $row->seatDepth,
            'seat_height' => $row->seatHeight,
            'armrest_width' => $row->armrestWidth,
            'leg_height' => $row->legHeight,
            'frame_spec' => $row->frameSpec,
            'mechanism' => $row->mechanism,
            'filling_spec' => $row->fillingSpec,
            'additional' => $row->additional,
            'frame' => $row->frame,
            'foundation' => $row->foundation,
            'filling' => $row->filling,
            'upholstery' => $row->upholstery,
            'supports' => $row->supports,
        ];

        foreach ($map as $attribute => $value) {
            if ($value !== null && $value !== '') {
                $model->{$attribute} = $value;
            }
        }

        $categorySlug = trim($categorySlug);

        if ($row->filterFunction !== CatalogFilterFunction::NONE) {
            $this->filterFunctionApplier->apply($model, $row->filterFunction, $categorySlug);
            $this->filterFunctionApplier->applySleepingPlaceSize(
                $model,
                $row->filterFunction,
                $categorySlug,
                $row->sleepingPlaceSize,
            );
        }
    }
}
