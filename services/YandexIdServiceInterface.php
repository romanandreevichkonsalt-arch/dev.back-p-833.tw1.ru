<?php

namespace app\services;

interface YandexIdServiceInterface
{
    /**
     * @return array{id:string,email:?string,display_name:?string,first_name:?string,last_name:?string,login:?string,avatar_url:?string,raw:array<string,mixed>}
     */
    public function fetchProfileByAccessToken(string $accessToken): array;
}
