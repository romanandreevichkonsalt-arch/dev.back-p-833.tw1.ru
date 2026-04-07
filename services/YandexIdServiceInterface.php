<?php

namespace app\services;

interface YandexIdServiceInterface
{
    /**
     * @return array{id:string,email:?string,display_name:?string,raw:array<string,mixed>}
     */
    public function fetchProfileByAccessToken(string $accessToken): array;
}
