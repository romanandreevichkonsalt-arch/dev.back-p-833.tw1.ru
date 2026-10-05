<?php

namespace app\services\moodboard;

use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSurfaceMaterial;
use app\models\MoodboardItem;
use app\models\MoodboardObjectType;

final class MoodboardItemEnricher
{
    /**
     * @return array<string, mixed>|null
     */
    public function enrichRef(MoodboardItem $item): ?array
    {
        return match ($item->object_type) {
            MoodboardObjectType::CODE_MODEL => $this->enrichModel($item->ref_id),
            MoodboardObjectType::CODE_FABRIC => $this->enrichFabric($item->ref_id),
            MoodboardObjectType::CODE_SURFACE_MATERIAL => $this->enrichSurfaceMaterial($item->ref_id),
            MoodboardObjectType::CODE_PRODUCT => $this->enrichProduct($item->ref_id),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrichModel(int $refId): ?array
    {
        $model = CatalogModel::find()
            ->with(['collection', 'category', 'modelImages.media', 'modelInteriorImages.media'])
            ->where(['id' => $refId])
            ->one();
        if ($model === null) {
            return null;
        }

        return $model->toMoodboardPickerApiItem();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrichFabric(int $refId): ?array
    {
        $fabric = CatalogFabricColor::find()
            ->with(['fabricCollection', 'catalogColor', 'swatchMedia'])
            ->where(['id' => $refId])
            ->one();
        if ($fabric === null) {
            return null;
        }

        $payload = $fabric->toApiPayload();
        $payload['fabricColorId'] = (int)$fabric->id;
        $swatches = $fabric->collectSwatchesApiPayload();
        $payload['swatch'] = $swatches[0] ?? null;

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrichSurfaceMaterial(int $refId): ?array
    {
        $material = CatalogSurfaceMaterial::find()
            ->with(['photoMedia', 'textureMedia'])
            ->where(['id' => $refId])
            ->one();
        if ($material === null) {
            return null;
        }

        $photo = $material->photoMedia?->toApiImagePayload($material->name);
        $texture = $material->textureMedia?->toApiImagePayload($material->name);

        return [
            'slug' => $material->slug,
            'name' => $material->name,
            'materialType' => $material->material_type,
            'photo' => $photo,
            'texture' => $texture,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrichProduct(int $refId): ?array
    {
        $product = CatalogProduct::find()
            ->with(['catalogModel.modelImages.media', 'fabricColor'])
            ->where(['id' => $refId])
            ->one();
        if ($product === null) {
            return null;
        }

        $image = $product->resolvePrimaryImagePayload();

        return [
            'slug' => $product->slug,
            'title' => $product->title,
            'href' => $product->href,
            'image' => $image,
        ];
    }
}
