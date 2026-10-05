<?php

namespace app\modules\admin\models;

use app\models\User;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class UserSearch extends Model
{
    public ?string $q = null;

    public function rules(): array
    {
        return [
            [['q'], 'string'],
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
        $query = User::find()
            ->alias('u')
            ->joinWith('profile p')
            ->where(['u.type' => User::TYPE_CUSTOMER])
            ->orderBy(['u.id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        if ($this->q !== null && $this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'u.phone', $this->q],
                ['like', 'u.username', $this->q],
                ['like', 'p.email', $this->q],
                ['like', 'p.display_name', $this->q],
                ['like', 'p.first_name', $this->q],
                ['like', 'p.last_name', $this->q],
            ]);
        }

        return $provider;
    }
}
