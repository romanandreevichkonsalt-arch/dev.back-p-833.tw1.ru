<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogPriceCategory extends ActiveRecord
{
    public const DEFAULT_COUNT = 8;

    public static function tableName(): string
    {
        return '{{%catalog_price_categories}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['number', 'label'], 'required'],
            [['number', 'sort_order', 'price_min', 'price_max', 'price_min_line1', 'price_max_line1'], 'integer', 'min' => 0],
            [['number'], 'unique'],
            [['label', 'label_line1'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'number' => 'Номер',
            'label' => 'Название',
            'price_min' => 'От, ₽',
            'price_max' => 'До, ₽',
            'label_line1' => 'Название (Линия 1)',
            'price_min_line1' => 'От, ₽ (Линия 1)',
            'price_max_line1' => 'До, ₽ (Линия 1)',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->isNewRecord && ($this->number === null || $this->number === '')) {
            $max = (int)static::find()->max('number');
            $this->number = max(1, $max + 1);
        }

        if ($this->sort_order === null || $this->sort_order === '') {
            $this->sort_order = (int)$this->number - 1;
        }

        if ($this->isNewRecord && trim((string)$this->label) === '') {
            $this->label = 'Категория ' . (int)$this->number;
        }

        foreach (['price_min', 'price_max', 'price_min_line1', 'price_max_line1'] as $attribute) {
            if ($this->{$attribute} === '' || $this->{$attribute} === null) {
                $this->{$attribute} = null;
                continue;
            }
            $this->{$attribute} = (int)$this->{$attribute};
        }

        return parent::beforeValidate();
    }

    /**
     * @return static[]
     */
    public static function findFabricOrdered(bool $activeOnly = false): array
    {
        $query = static::find()
            ->orderBy(['sort_order' => SORT_ASC, 'number' => SORT_ASC, 'id' => SORT_ASC]);

        if ($activeOnly) {
            $query->andWhere(['is_active' => true]);
        }

        return $query->all();
    }

    /**
     * @return static[]
     */
    public static function findActiveOrdered(): array
    {
        return static::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'number' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    public static function getDefaultId(): ?int
    {
        $category = static::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'number' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        return $category !== null ? (int)$category->id : null;
    }

    public function getDisplayLabel(): string
    {
        if ($this->label !== '') {
            return $this->label;
        }

        return 'Категория ' . $this->number;
    }

    public function getDisplayLabelLine1(): string
    {
        if ($this->label_line1 !== null && trim($this->label_line1) !== '') {
            return $this->label_line1;
        }

        return 'Категория ' . $this->number;
    }

    public function getRangeLabel(): string
    {
        return self::formatRangeLabel($this->price_min, $this->price_max);
    }

    public function getRangeLabelLine1(): string
    {
        return self::formatRangeLabel($this->price_min_line1, $this->price_max_line1);
    }

    private static function formatRangeLabel(?int $priceMin, ?int $priceMax): string
    {
        if ($priceMin !== null && $priceMax !== null) {
            return sprintf('от %d до %d руб', $priceMin, $priceMax);
        }
        if ($priceMin !== null) {
            return sprintf('от %d руб', $priceMin);
        }
        if ($priceMax !== null) {
            return sprintf('до %d руб', $priceMax);
        }

        return '';
    }
}
