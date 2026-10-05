<?php

namespace app\services\payment;

/**
 * Validates that a request IP belongs to YooKassa webhook ranges.
 *
 * @see https://yookassa.ru/developers/using-api/webhooks
 */
final class YooKassaWebhookIpAllowlist
{
    /**
     * @param list<string> $cidrs
     */
    public static function isAllowed(string $ip, array $cidrs): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }

        foreach ($cidrs as $cidr) {
            $cidr = trim((string)$cidr);
            if ($cidr === '') {
                continue;
            }
            if (self::match($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function match(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $maskBits = (int)$maskBits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $len = strlen($ipBin);
        $maxBits = $len * 8;
        if ($maskBits < 0 || $maskBits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($maskBits, 8);
        $remainBits = $maskBits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainBits)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }
}
