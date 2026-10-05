<?php

namespace app\commands;

use app\services\dealer\CashbackService;
use Yii;
use yii\console\Controller;

class CashbackController extends Controller
{
    public function actionAccrueMonthly(?string $period = null): int
    {
        $period = $period ?? date('Y-m', strtotime('first day of previous month'));
        $count = (new CashbackService())->accrueMonthlyForAll($period);
        $this->stdout("Начислен кэшбек {$count} дилерам за {$period}.\n");

        return self::EXIT_CODE_NORMAL;
    }

    public function actionExpire(): int
    {
        $count = (new CashbackService())->expireDueEntries();
        $this->stdout("Списано просроченных начислений: {$count}.\n");

        return self::EXIT_CODE_NORMAL;
    }

    public function actionNotifyExpiring(?int $days = null): int
    {
        $daysBefore = $days ?? (int)(Yii::$app->params['cashback']['notifyDaysBefore'] ?? 7);
        $count = (new CashbackService())->notifyExpiringSoon($daysBefore);
        $this->stdout("Отправлено напоминаний о сгорании кэшбека: {$count} (за {$daysBefore} дн.).\n");

        return self::EXIT_CODE_NORMAL;
    }
}
