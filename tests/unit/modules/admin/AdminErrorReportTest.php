<?php

namespace tests\unit\modules\admin;

use app\modules\admin\helpers\AdminErrorReport;
use Codeception\Test\Unit;
use yii\web\NotFoundHttpException;

class AdminErrorReportTest extends Unit
{
    public function testStatusCodeFromHttpException(): void
    {
        verify(AdminErrorReport::statusCode(new NotFoundHttpException('missing')))->equals(404);
    }

    public function testDeveloperTextContainsExceptionClass(): void
    {
        $text = AdminErrorReport::developerText(new NotFoundHttpException('missing'));
        verify($text)->stringContainsString('NotFoundHttpException');
        verify($text)->stringContainsString('missing');
    }
}
