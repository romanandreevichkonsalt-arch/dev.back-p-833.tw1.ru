<?php

namespace app\services\import\catalog;

use app\models\CatalogImportRun;
use app\models\CatalogModel;
use app\services\catalog\CatalogFilterFunction;
use app\services\catalog\CatalogModelProductSyncService;

class CatalogModelImporter
{
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

        $rows = $this->reader->read($filePath);
        $totalRows = count($rows);

        if ($importRun !== null) {
            $importRun->total_rows = $totalRows;
            $importRun->save(false, ['total_rows']);
        }

        if ($startIndex === 0) {
            $maxCategory = 1;
            foreach ($rows as $row) {
                foreach (array_keys($row->pricesByCategoryNumber) as $number) {
                    $maxCategory = max($maxCategory, (int)$number);
                }
            }

            if ($options->shouldWrite()) {
                $this->resolver->ensurePriceCategoriesUpTo($maxCategory);
            }
        }

        if ($startIndex > 0) {
            $rows = array_slice($rows, $startIndex);
        }

        foreach ($rows as $offset => $row) {
            if ($result->aborted) {
                break;
            }

            $absoluteIndex = $startIndex + $offset;
            $rowResult = new CatalogModelImportRowResult();
            $rowResult->rowNumber = $row->rowNumber;
            $rowResult->collectionName = $row->collectionName;
            $rowResult->modelLabel = $row->displayLabel;

            try {
                $this->importRow($row, $options, $result, $rowResult, $importRun);
            } catch (\Throwable $exception) {
                $rowResult->action = 'error';
                $rowResult->messages[] = $exception->getMessage();
                $result->stats['errors']++;
                $result->success = false;
            }

            $result->rows[] = $rowResult;

            if ($result->aborted && $rowResult->action === 'conflict') {
                if ($options->asyncMode && $importRun !== null) {
                    $importRun->resume_row_index = $absoluteIndex;
                    $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_AWAITING_CONFLICT);
                }
                break;
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

        if ($importRun !== null && $options->asyncMode && !($result->aborted && $importRun->status === CatalogImportRun::STATUS_AWAITING_CONFLICT)) {
            $finalStatus = $result->aborted
                ? CatalogImportRun::STATUS_ABORTED
                : ($options->dryRun ? 'dry_run' : CatalogImportRun::STATUS_COMPLETED);
            $this->persistRunState($importRun, $result, $finalStatus);
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
    ): void {
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
            ? CatalogModel::find()
                ->where([
                    'collection_id' => $collection->id,
                    'subcategory_id' => $subcategory->id,
                ])
                ->one()
            : null;

        if ($existing !== null && !$options->updateExisting && $options->resolveConflictAction() !== CatalogModelImportOptions::CONFLICT_UPDATE) {
            $conflictAction = $options->resolveConflictAction();

            if ($conflictAction === CatalogModelImportOptions::CONFLICT_SKIP) {
                if (!$options->dryRun && $row->fabricCollectionNames !== []) {
                    $this->syncFabricCollections($existing, $row, $rowResult);
                    $this->syncService->syncForModel($existing);
                    $rowResult->messages[] = 'Привязки тканей обновлены.';
                }
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

        if ($options->skipIfNoFabric && $existing === null && !$this->hasResolvableFabric($row)) {
            $rowResult->action = 'skipped';
            $rowResult->messages[] = $row->fabricCollectionNames === []
                ? 'Строка пропущена: не указана ткань.'
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
        $this->syncFabricCollections($model, $row, $rowResult);
        foreach ($this->mediaImport->applyToModel($model, $row, $options->importMedia) as $mediaWarning) {
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
        $this->syncService->syncForModel($model);

        $rowResult->action = $isNew ? 'created' : 'updated';
        $rowResult->messages[] = ($isNew ? 'Создана' : 'Обновлена') . ' модель с ' . count($pricesByCategoryId) . ' ценами.';
        if ($isNew) {
            $result->stats['models_created']++;
        } else {
            $result->stats['models_updated']++;
        }

        $this->clearConflictResolutionOnce($options, $importRun);
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

    private function syncFabricCollections(
        CatalogModel $model,
        CatalogModelImportRowDto $row,
        CatalogModelImportRowResult $rowResult
    ): void {
        if ($row->fabricCollectionNames === []) {
            return;
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

        if ($fabricIds !== []) {
            $model->syncFabricCollectionLinks($fabricIds);
        }
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
