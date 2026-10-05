<?php

namespace app\services\moodboard;

use app\exceptions\ApiValidationException;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSurfaceMaterial;
use app\models\MoodboardObjectType;

final class MoodboardItemRefResolver
{
    /**
     * @return array{refId: int, refSlug: string}
     */
    public function resolve(string $objectType, mixed $refSlug, mixed $refId = null): array
    {
        $objectType = trim($objectType);
        if (!in_array($objectType, MoodboardObjectType::ACTIVE_CODES, true)) {
            throw new ApiValidationException('Неизвестный тип объекта.', [
                'objectType' => ['Допустимые значения: ' . implode(', ', MoodboardObjectType::ACTIVE_CODES)],
            ]);
        }

        return match ($objectType) {
            MoodboardObjectType::CODE_MODEL => $this->resolveModel($refSlug, $refId),
            MoodboardObjectType::CODE_FABRIC => $this->resolveFabric($refSlug, $refId),
            MoodboardObjectType::CODE_SURFACE_MATERIAL => $this->resolveSurfaceMaterial($refSlug, $refId),
            MoodboardObjectType::CODE_PRODUCT => $this->resolveProduct($refSlug, $refId),
            default => throw new ApiValidationException('Неизвестный тип объекта.'),
        };
    }

    /**
     * @return array{refId: int, refSlug: string}
     */
    private function resolveModel(mixed $refSlug, mixed $refId): array
    {
        $slug = trim((string)($refSlug ?? ''));
        if ($slug === '') {
            throw new ApiValidationException('Укажите refSlug модели.', ['refSlug' => ['Обязательное поле.']]);
        }

        $model = CatalogModel::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->one();
        if ($model === null) {
            throw new ApiValidationException('Модель не найдена.', ['refSlug' => ['Модель не найдена или неактивна.']]);
        }

        return ['refId' => (int)$model->id, 'refSlug' => (string)$model->slug];
    }

    /**
     * @return array{refId: int, refSlug: string}
     */
    private function resolveFabric(mixed $refSlug, mixed $refId): array
    {
        $id = $this->intOrNull($refId);
        if ($id === null && is_numeric($refSlug)) {
            $id = (int)$refSlug;
        }

        $fabric = null;
        if ($id !== null) {
            $fabric = CatalogFabricColor::find()
                ->where(['id' => $id, 'is_active' => true])
                ->one();
        }

        if ($fabric === null) {
            throw new ApiValidationException('Ткань не найдена.', [
                'refId' => ['Передайте refId активного цвета ткани.'],
            ]);
        }

        return [
            'refId' => (int)$fabric->id,
            'refSlug' => (string)$fabric->id,
        ];
    }

    /**
     * @return array{refId: int, refSlug: string}
     */
    private function resolveSurfaceMaterial(mixed $refSlug, mixed $refId): array
    {
        $slug = trim((string)($refSlug ?? ''));
        if ($slug === '') {
            throw new ApiValidationException('Укажите refSlug материала.', ['refSlug' => ['Обязательное поле.']]);
        }

        $material = CatalogSurfaceMaterial::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->one();
        if ($material === null) {
            throw new ApiValidationException('Материал не найден.', ['refSlug' => ['Материал не найден или неактивен.']]);
        }

        return ['refId' => (int)$material->id, 'refSlug' => (string)$material->slug];
    }

    /**
     * @return array{refId: int, refSlug: string}
     */
    private function resolveProduct(mixed $refSlug, mixed $refId): array
    {
        $slug = trim((string)($refSlug ?? ''));
        if ($slug === '') {
            throw new ApiValidationException('Укажите refSlug товара.', ['refSlug' => ['Обязательное поле.']]);
        }

        $product = CatalogProduct::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->one();
        if ($product === null) {
            throw new ApiValidationException('Товар не найден.', ['refSlug' => ['Товар не найден или неактивен.']]);
        }

        return ['refId' => (int)$product->id, 'refSlug' => (string)$product->slug];
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $int = (int)$value;

        return $int > 0 ? $int : null;
    }
}
