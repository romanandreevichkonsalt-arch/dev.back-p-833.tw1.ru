<?php

namespace app\modules\admin\models;

use app\models\Lead;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class LeadSearch extends Model
{
    public ?string $type = null;
    public ?string $status = null;
    public ?string $q = null;

    public function rules(): array
    {
        return [
            [['type', 'status', 'q'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'type' => 'Тип',
            'status' => 'Статус',
            'q' => 'Поиск',
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Lead::find()->with(['assignee'])->orderBy(['id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        $query->andFilterWhere(['type' => $this->type, 'status' => $this->status]);

        if ($this->q !== null && $this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'name', $this->q],
                ['like', 'phone', $this->q],
                ['like', 'email', $this->q],
                ['like', 'studio', $this->q],
                ['like', 'city', $this->q],
                ['like', 'comment', $this->q],
            ]);
        }

        return $provider;
    }
}
