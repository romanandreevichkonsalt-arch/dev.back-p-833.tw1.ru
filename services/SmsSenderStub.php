<?php

namespace app\services;

use Yii;

class SmsSenderStub implements SmsSenderInterface
{
    public function sendCode(string $phone, string $code): void
    {
        Yii::info(
            "SMS stub: code {$code} sent to {$phone}",
            __METHOD__
        );
    }
}
