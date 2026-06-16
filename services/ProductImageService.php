<?php

namespace app\services;

use app\models\Product;
use app\models\ProductImage;
use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class ProductImageService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @param UploadedFile[] $files
     * @return ProductImage[]
     */
    public function uploadForProduct(Product $product, array $files): array
    {
        $saved = [];
        $uploadDir = $this->getUploadDir((int) $product->id);
        FileHelper::createDirectory($uploadDir);

        $hasMain = ProductImage::find()->where(['product_id' => $product->id, 'is_main' => true])->exists();
        $sortOrder = (int) ProductImage::find()->where(['product_id' => $product->id])->max('sort_order');

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || $file->error !== UPLOAD_ERR_OK) {
                continue;
            }

            $extension = strtolower((string) $file->extension);
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $filename = Yii::$app->security->generateRandomString(16) . '.' . $extension;
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (!$file->saveAs($fullPath)) {
                continue;
            }

            $sortOrder++;
            $image = new ProductImage([
                'product_id' => (int) $product->id,
                'path' => 'uploads/products/' . $product->id . '/' . $filename,
                'alt' => $product->name,
                'sort_order' => $sortOrder,
                'is_main' => !$hasMain,
            ]);
            if ($image->save()) {
                $hasMain = $hasMain || $image->is_main;
                $saved[] = $image;
            }
        }

        return $saved;
    }

    public function deleteImage(ProductImage $image): bool
    {
        $fullPath = Yii::getAlias('@webroot/' . ltrim($image->path, '/'));
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        return (bool) $image->delete();
    }

    public function setMain(ProductImage $image): void
    {
        ProductImage::updateAll(['is_main' => false], ['product_id' => $image->product_id]);
        $image->is_main = true;
        $image->save(false, ['is_main']);
    }

    private function getUploadDir(int $productId): string
    {
        return Yii::getAlias('@webroot/uploads/products/' . $productId);
    }
}
