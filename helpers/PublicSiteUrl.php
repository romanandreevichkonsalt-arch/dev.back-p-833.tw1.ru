<?php

namespace app\helpers;

final class PublicSiteUrl
{
    public const DEFAULT_FRONTEND = 'https://dev-fabrika.ru';

    public static function frontendUrl(?string $envValue = null): string
    {
        if ($envValue === null) {
            $envValue = getenv('FRONTEND_URL') ?: '';
        }

        $url = rtrim(trim($envValue), '/');

        return $url !== '' ? $url : self::DEFAULT_FRONTEND;
    }

    public static function dealerCabinetUrl(?string $envFrontend = null, ?string $envOverride = null): string
    {
        if ($envOverride === null) {
            $envOverride = getenv('DEALER_CABINET_URL') ?: '';
        }

        $override = rtrim(trim($envOverride), '/');
        if ($override !== '') {
            return $override;
        }

        return self::frontendUrl($envFrontend);
    }
}
