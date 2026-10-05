<?php

namespace app\modules\admin\models;

use app\models\Order;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class OrderSearch extends Model
{
    public ?string $status = null;
    public ?string $q = null;

    public function rules(): array
    {
        return [
            [['status', 'q'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'status' => 'Статус',
            'q' => 'Поиск',
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Order::find()->orderBy(['id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        $query->andFilterWhere(['status' => $this->status]);

        if ($this->q !== null && $this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'number', $this->q],
                ['like', 'customer_name', $this->q],
                ['like', 'customer_phone', $this->q],
            ]);
        }

        return $provider;
    }
}
