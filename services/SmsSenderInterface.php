<?php

namespace app\services;

interface SmsSenderInterface
{
    public function sendCode(string $phone, string $code): void;
}
