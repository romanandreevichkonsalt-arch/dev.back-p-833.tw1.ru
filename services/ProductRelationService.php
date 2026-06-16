<?php

namespace app\services;

use app\models\AttributeDefinition;
use app\models\Category;
use app\models\Product;
use app\models\ProductAttribute;
use app\models\ProductCategory;
use Yii;
use yii\web\BadRequestHttpException;

class ProductRelationService
{
    /**
     * @param int[] $categoryIds
     */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if ($categoryIds === []) {
            throw new BadRequestHttpException('Необходимо указать хотя бы одну категорию.');
        }

        $existingCount = Category::find()->where(['id' => $categoryIds])->count();
        if ((int) $existingCount !== count($categoryIds)) {
            throw new BadRequestHttpException('Одна или несколько категорий не найдены.');
        }

        ProductCategory::deleteAll(['product_id' => $product->id]);
        foreach ($categoryIds as $categoryId) {
            $link = new ProductCategory([
                'product_id' => (int) $product->id,
                'category_id' => $categoryId,
            ]);
            if (!$link->save()) {
                throw new BadRequestHttpException('Не удалось привязать категории к товару.');
            }
        }
    }

    /**
     * @param array<int,array{code:string,value:mixed}> $attributes
     */
    public function syncAttributes(Product $product, array $attributes): void
    {
        $definitions = AttributeDefinition::mapByCode();
        $providedCodes = [];

        foreach ($attributes as $item) {
            if (!is_array($item) || empty($item['code'])) {
                throw new BadRequestHttpException('Каждый атрибут должен содержать code и value.');
            }

            $code = (string) $item['code'];
            $definition = $definitions[$code] ?? null;
            if ($definition === null) {
                throw new BadRequestHttpException("Атрибут '{$code}' не определён.");
            }

            $providedCodes[] = $code;
            $attribute = ProductAttribute::find()
                ->where(['product_id' => $product->id, 'attribute_code' => $code])
                ->one();

            if ($attribute === null) {
                $attribute = new ProductAttribute([
                    'product_id' => (int) $product->id,
                    'attribute_code' => $code,
                ]);
            }

            $valueColumns = ProductAttribute::valueColumnsForType($definition->type, $item['value'] ?? null);
            $attribute->setAttributes($valueColumns, false);

            if (!$attribute->save()) {
                throw new BadRequestHttpException('Не удалось сохранить атрибуты товара.');
            }
        }

        foreach ($definitions as $code => $definition) {
            if ($definition->is_required && !in_array($code, $providedCodes, true)) {
                throw new BadRequestHttpException("Обязательный атрибут '{$code}' не указан.");
            }
        }

        if ($providedCodes !== []) {
            ProductAttribute::deleteAll([
                'and',
                ['product_id' => $product->id],
                ['not in', 'attribute_code', $providedCodes],
            ]);
        }
    }

    public function saveProductWithRelations(Product $product, array $body): Product
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$product->save()) {
                throw new BadRequestHttpException($this->formatErrors($product->getErrors()));
            }

            if (array_key_exists('category_ids', $body)) {
                $this->syncCategories($product, (array) $body['category_ids']);
            }

            if (array_key_exists('attributes', $body)) {
                $this->syncAttributes($product, (array) $body['attributes']);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $product->refresh();

        return $product;
    }

    /**
     * @param array<string,array<int,string>> $errors
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = $message;
            }
        }

        return $messages !== [] ? implode(' ', $messages) : 'Ошибка валидации.';
    }
}
