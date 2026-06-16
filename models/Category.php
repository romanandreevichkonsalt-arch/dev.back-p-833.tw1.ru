<?php

namespace app\models;

use app\behaviors\SoftDeleteBehavior;
use app\helpers\SlugHelper;
use app\models\traits\SoftDeleteTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_h1
 * @property bool $is_active
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @property Category|null $parent
 * @property Category[] $children
 * @property Product[] $products
 */
class Category extends ActiveRecord
{
    use SoftDeleteTrait;

    public static function tableName(): string
    {
        return '{{%categories}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
            SoftDeleteBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'slug'], 'required'],
            [['parent_id', 'sort_order'], 'integer'],
            [['description'], 'string'],
            [['is_active'], 'boolean'],
            [['name', 'slug', 'seo_title', 'seo_h1'], 'string', 'max' => 255],
            [['seo_description'], 'string', 'max' => 512],
            [['slug'], 'unique'],
            [['slug'], 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'message' => 'Slug должен быть в формате kebab-case.'],
            [
                ['parent_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => self::class,
                'targetAttribute' => ['parent_id' => 'id'],
            ],
            [['parent_id'], 'validateNoCycle'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Родитель',
            'name' => 'Название',
            'slug' => 'Slug',
            'description' => 'Описание',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'seo_h1' => 'SEO H1',
            'is_active' => 'Активна',
            'sort_order' => 'Порядок',
            'created_at' => 'Создана',
            'updated_at' => 'Обновлена',
        ];
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        if (($this->slug === null || $this->slug === '') && $this->name !== '') {
            $baseSlug = SlugHelper::fromString($this->name);
            $this->slug = SlugHelper::ensureUnique($baseSlug, function (string $slug): bool {
                $query = self::find()->where(['slug' => $slug]);
                if (!$this->isNewRecord) {
                    $query->andWhere(['<>', 'id', $this->id]);
                }

                return $query->exists();
            });
        }

        return true;
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::find()->where(['slug' => $slug])->one();
    }

    public function getDisplayLabel(): string
    {
        return $this->name . ' (' . $this->slug . ')';
    }

    public function validateNoCycle(string $attribute): void
    {
        if ($this->parent_id === null || $this->isNewRecord) {
            return;
        }

        if ((int) $this->parent_id === (int) $this->id) {
            $this->addError($attribute, 'Категория не может быть родителем самой себя.');

            return;
        }

        $descendantIds = self::collectDescendantIds((int) $this->id);
        if (in_array((int) $this->parent_id, $descendantIds, true)) {
            $this->addError($attribute, 'Нельзя назначить потомка в качестве родителя.');
        }
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren(): ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC]);
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])
            ->viaTable('{{%product_categories}}', ['category_id' => 'id']);
    }

    public function toApiArray(bool $withChildren = false): array
    {
        $data = [
            'id' => (int) $this->id,
            'parent_id' => $this->parent_id !== null ? (int) $this->parent_id : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_h1' => $this->seo_h1,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($withChildren) {
            $data['children'] = array_map(
                static fn (self $child): array => $child->toApiArray(true),
                $this->children
            );
        }

        return $data;
    }

    public function toTreeNode(): array
    {
        return [
            'id' => (int) $this->id,
            'parent_id' => $this->parent_id !== null ? (int) $this->parent_id : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'children' => array_map(
                static fn (self $child): array => $child->toTreeNode(),
                $this->children
            ),
        ];
    }

    /**
     * @return int[]
     */
    public static function collectDescendantIds(int $categoryId): array
    {
        $ids = [];
        $children = self::find()->select('id')->where(['parent_id' => $categoryId])->column();
        foreach ($children as $childId) {
            $childId = (int) $childId;
            $ids[] = $childId;
            $ids = array_merge($ids, self::collectDescendantIds($childId));
        }

        return $ids;
    }

    public function hasChildren(): bool
    {
        return self::find()->where(['parent_id' => $this->id])->exists();
    }

    public function hasProducts(): bool
    {
        return ProductCategory::find()->where(['category_id' => $this->id])->exists();
    }
}
