<?php

namespace app\modules\admin\controllers;

use app\models\Lead;
use app\models\Order;
use Yii;

class DashboardController extends BaseController
{
    public function actionIndex(): string
    {
        $this->requirePermission('dashboard');

        $today = date('Y-m-d 00:00:00');

        $newLeadsCount = Lead::find()
            ->where(['>=', 'created_at', $today])
            ->count();

        $openLeadsCount = Lead::find()
            ->where(['status' => [Lead::STATUS_NEW, Lead::STATUS_IN_PROGRESS]])
            ->count();

        $newOrdersCount = Order::find()
            ->where(['>=', 'created_at', $today])
            ->count();

        $openOrdersCount = Order::find()
            ->where(['not in', 'status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED]])
            ->count();

        $recentLeads = Lead::find()->orderBy(['id' => SORT_DESC])->limit(5)->all();
        $recentOrders = Order::find()->orderBy(['id' => SORT_DESC])->limit(5)->all();

        return $this->render('index', [
            'newLeadsCount' => $newLeadsCount,
            'openLeadsCount' => $openLeadsCount,
            'newOrdersCount' => $newOrdersCount,
            'openOrdersCount' => $openOrdersCount,
            'totalLeadsCount' => Lead::find()->count(),
            'totalOrdersCount' => Order::find()->count(),
            'recentLeads' => $recentLeads,
            'recentOrders' => $recentOrders,
        ]);
    }
}
