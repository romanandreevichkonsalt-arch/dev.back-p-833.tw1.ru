<?php

namespace app\modules\admin\models;

use app\models\User;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class DealerSearch extends Model
{
    public ?string $q = null;

    /** @var bool Архив: только заблокированные дилеры */
    public bool $archive = false;

    public function rules(): array
    {
        return [
            [['q'], 'string'],
            [['archive'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'q' => 'Поиск',
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $this->load($params);
        $this->archive = filter_var($params['archive'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (string)($params['archive'] ?? '') === '1';

        $query = User::find()
            ->alias('u')
            ->innerJoinWith('dealerProfile dp')
            ->where(['u.type' => User::TYPE_DEALER])
            ->andWhere(['u.is_blocked' => $this->archive])
            ->orderBy(['u.id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        if (!$this->validate()) {
            return $provider;
        }

        if ($this->q !== null && $this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'u.username', $this->q],
                ['like', 'u.phone', $this->q],
                ['like', 'dp.inn', $this->q],
                ['like', 'dp.company_name', $this->q],
                ['like', 'dp.manager_name', $this->q],
                ['like', 'dp.email', $this->q],
            ]);
        }

        return $provider;
    }
}
