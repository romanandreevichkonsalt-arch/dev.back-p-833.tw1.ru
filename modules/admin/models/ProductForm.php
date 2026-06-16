<?php

namespace app\modules\admin\models;

use app\models\AttributeDefinition;
use app\models\Product;
use app\models\ProductAttribute;
use app\services\ProductRelationService;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

class ProductForm extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';
    public string $sku = '';
    public ?string $description = null;
    public float $price = 0;
    public ?float $old_price = null;
    public bool $is_active = true;
    public int $stock_qty = 0;
    public ?string $seo_title = null;
    public ?string $seo_description = null;
    public ?string $seo_h1 = null;
    /** @var int[] */
    public array $category_ids = [];
    /** @var array<int,array{code:string,value:mixed}> */
    public array $productAttributeValues = [];

    public function rules(): array
    {
        return [
            [['name', 'slug', 'sku'], 'required'],
            [['name', 'slug', 'sku', 'seo_title', 'seo_h1'], 'string', 'max' => 255],
            [['seo_description'], 'string', 'max' => 512],
            [['description'], 'string'],
            [['price', 'old_price'], 'number', 'min' => 0],
            [['stock_qty'], 'integer', 'min' => 0],
            [['is_active'], 'boolean'],
            [['category_ids', 'productAttributeValues'], 'safe'],
            [['category_ids'], 'validateCategoryIds'],
        ];
    }

    public function validateCategoryIds(string $attribute): void
    {
        if ($this->category_ids === []) {
            $this->addError($attribute, 'Выберите хотя бы одну категорию.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'slug' => 'Slug',
            'sku' => 'SKU',
            'description' => 'Описание',
            'price' => 'Цена',
            'old_price' => 'Старая цена',
            'is_active' => 'Активен',
            'stock_qty' => 'Остаток',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'seo_h1' => 'SEO H1',
            'category_ids' => 'Категории',
        ];
    }

    public static function fromProduct(Product $product): self
    {
        $form = new self();
        $form->id = (int) $product->id;
        $form->setAttributes($product->getAttributes(), false);
        $form->category_ids = array_map('intval', $product->getCategories()->select('id')->column());
        $form->productAttributeValues = [];
        foreach ($product->productAttributes as $attribute) {
            $definition = $attribute->definition;
            $type = $definition ? $definition->type : AttributeDefinition::TYPE_STRING;
            $form->productAttributeValues[$attribute->attribute_code] = $attribute->getTypedValue($type);
        }

        return $form;
    }

    public function save(): ?Product
    {
        if (!$this->validate()) {
            return null;
        }

        $product = $this->id ? Product::findOne($this->id) : new Product();
        if ($product === null) {
            return null;
        }

        $product->setAttributes($this->getAttributes(null, ['id', 'category_ids', 'productAttributeValues']), false);

        /** @var ProductRelationService $service */
        $service = Yii::createObject(ProductRelationService::class);

        try {
            $product = $service->saveProductWithRelations($product, [
                'category_ids' => $this->category_ids,
                'attributes' => $this->buildAttributesPayload(),
            ]);
        } catch (\Throwable $e) {
            $this->addError('name', $e->getMessage());

            return null;
        }

        $this->id = (int) $product->id;

        return $product;
    }

    /**
     * @return array<int,array{code:string,value:mixed}>
     */
    private function buildAttributesPayload(): array
    {
        $payload = [];
        $definitions = AttributeDefinition::mapByCode();
        $posted = Yii::$app->request->post('ProductForm', []);
        $rawAttributes = $posted['productAttributeValues'] ?? $this->productAttributeValues;

        if (!is_array($rawAttributes)) {
            return [];
        }

        foreach ($rawAttributes as $code => $value) {
            if (!is_string($code) || !isset($definitions[$code])) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $definition = $definitions[$code];
            if ($definition->type === AttributeDefinition::TYPE_BOOL) {
                $value = (bool) $value;
            } elseif ($definition->type === AttributeDefinition::TYPE_NUMBER) {
                $value = (float) $value;
            }
            $payload[] = ['code' => $code, 'value' => $value];
        }

        return $payload;
    }
}
