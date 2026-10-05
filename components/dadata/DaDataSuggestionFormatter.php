<?php

declare(strict_types=1);

namespace app\components\dadata;

final class DaDataSuggestionFormatter
{
    /** @param array<string, mixed> $row */
    public static function format(array $row): array
    {
        $data = is_array($row['data'] ?? null) ? $row['data'] : [];
        $postalCode = trim((string) ($data['postal_code'] ?? ''));
        $value = (string) ($row['value'] ?? '');
        $unrestricted = trim((string) ($row['unrestricted_value'] ?? $value));

        return [
            'value' => $value,
            'full_address' => self::buildFullAddress($postalCode, $unrestricted),
            'postal_code' => $postalCode !== '' ? $postalCode : null,
            'city_name' => self::resolveCityName($data),
            'data' => [
                'city_fias_id' => $data['city_fias_id'] ?? null,
                'address_fias_id' => $data['address_fias_id'] ?? $data['fias_id'] ?? null,
                'house_fias_id' => $data['house_fias_id'] ?? $data['fias_id'] ?? null,
                'geo_lat' => $data['geo_lat'] ?? null,
                'geo_lon' => $data['geo_lon'] ?? null,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $suggestions */
    public static function formatMany(array $suggestions): array
    {
        return array_map(static fn (array $row): array => self::format($row), $suggestions);
    }

    private static function buildFullAddress(string $postalCode, string $unrestricted): string
    {
        if ($unrestricted === '') {
            return $postalCode;
        }
        if ($postalCode === '') {
            return $unrestricted;
        }
        if (str_starts_with($unrestricted, $postalCode)) {
            return $unrestricted;
        }

        return $postalCode . ', ' . $unrestricted;
    }

    /** @param array<string, mixed> $data */
    private static function resolveCityName(array $data): ?string
    {
        foreach (['city_with_type', 'city', 'settlement_with_type', 'settlement'] as $key) {
            $name = trim((string) ($data[$key] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }
}
