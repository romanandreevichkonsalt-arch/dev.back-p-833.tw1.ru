<?php

declare(strict_types=1);

namespace app\components\dadata;

use Yii;

final class DaDataClient
{
    private const SUGGEST_URL = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest';

    /** @return array<int, array<string, mixed>> */
    public function suggestFullAddress(string $query, int $count): array
    {
        $result = $this->request('/address', [
            'query' => $query,
            'count' => $count,
        ]);

        if ($result !== null) {
            return $result;
        }

        return $this->mockFullAddresses($query, $count);
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestCity(string $query, int $count): array
    {
        $result = $this->request('/address', [
            'query' => $query,
            'count' => $count,
            'from_bound' => ['value' => 'city'],
            'to_bound' => ['value' => 'settlement'],
        ]);

        if ($result !== null) {
            return $result;
        }

        return $this->mockCities($query, $count);
    }

    /** @param array<string, mixed> $payload */
    private function request(string $path, array $payload): ?array
    {
        $apiKey = (string) (Yii::$app->params['dadataApiKey'] ?? '');
        if ($apiKey === '') {
            return null;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\nAuthorization: Token {$apiKey}\r\n",
                'content' => $body,
                'timeout' => 8,
            ],
        ]);

        $raw = @file_get_contents(self::SUGGEST_URL . $path, false, $context);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['suggestions']) || !is_array($decoded['suggestions'])) {
            return null;
        }

        return $decoded['suggestions'];
    }

    /** @return array<int, array<string, mixed>> */
    private function mockFullAddresses(string $query, int $count): array
    {
        $q = mb_strtolower(trim($query));
        $all = [
            [
                'value' => 'г Москва, ул Тверская, д 7',
                'unrestricted_value' => '125009, г Москва, ул Тверская, д 7',
                'data' => [
                    'postal_code' => '125009',
                    'city' => 'Москва',
                    'city_with_type' => 'г Москва',
                    'city_fias_id' => '0c5b2444-70a3-4b20-878f-b0f2b8daecf0',
                    'address_fias_id' => 'fias-id-1',
                    'house_fias_id' => 'fias-id-house-1',
                    'geo_lat' => '55.7641',
                    'geo_lon' => '37.6054',
                ],
            ],
            [
                'value' => 'г Санкт-Петербург, Невский пр-кт, д 28',
                'unrestricted_value' => '191186, г Санкт-Петербург, Невский пр-кт, д 28',
                'data' => [
                    'postal_code' => '191186',
                    'city' => 'Санкт-Петербург',
                    'city_with_type' => 'г Санкт-Петербург',
                    'city_fias_id' => 'c2deb16a-0330-4f05-821f-1d09c93331e6',
                    'address_fias_id' => 'fias-id-2',
                    'house_fias_id' => 'fias-id-house-2',
                    'geo_lat' => '59.9358',
                    'geo_lon' => '30.3259',
                ],
            ],
            [
                'value' => 'г Казань, ул Баумана, д 19',
                'unrestricted_value' => '420111, г Респ Татарстан, г Казань, ул Баумана, д 19',
                'data' => [
                    'postal_code' => '420111',
                    'city' => 'Казань',
                    'city_with_type' => 'г Казань',
                    'city_fias_id' => '93b3df57-4e5e-4b4e-8b0e-0e0e0e0e0e0e',
                    'address_fias_id' => 'fias-id-3',
                    'house_fias_id' => 'fias-id-house-3',
                    'geo_lat' => '55.7887',
                    'geo_lon' => '49.1221',
                ],
            ],
        ];

        $filtered = array_values(array_filter(
            $all,
            static fn (array $row): bool => $q === ''
                || str_contains(mb_strtolower($row['value']), $q)
                || str_contains(mb_strtolower($row['unrestricted_value']), $q),
        ));

        if ($filtered === [] && $q !== '') {
            $filtered[] = [
                'value' => $query,
                'unrestricted_value' => '101000, ' . $query,
                'data' => [
                    'postal_code' => '101000',
                    'city' => 'Москва',
                    'city_with_type' => 'г Москва',
                    'city_fias_id' => '0c5b2444-70a3-4b20-878f-b0f2b8daecf0',
                    'address_fias_id' => 'fias-id-mock',
                    'house_fias_id' => 'fias-id-house-mock',
                    'geo_lat' => '55.7558',
                    'geo_lon' => '37.6173',
                ],
            ];
        }

        return array_slice($filtered, 0, $count);
    }

    /** @return array<int, array<string, mixed>> */
    private function mockCities(string $query, int $count): array
    {
        $q = mb_strtolower(trim($query));
        $all = [
            [
                'value' => 'г Москва',
                'unrestricted_value' => 'г Москва',
                'data' => [
                    'postal_code' => '101000',
                    'city' => 'Москва',
                    'city_with_type' => 'г Москва',
                    'city_fias_id' => '0c5b2444-70a3-4b20-878f-b0f2b8daecf0',
                    'geo_lat' => '55.7558',
                    'geo_lon' => '37.6173',
                ],
            ],
            [
                'value' => 'г Санкт-Петербург',
                'unrestricted_value' => 'г Санкт-Петербург',
                'data' => [
                    'postal_code' => '190000',
                    'city' => 'Санкт-Петербург',
                    'city_with_type' => 'г Санкт-Петербург',
                    'city_fias_id' => 'c2deb16a-0330-4f05-821f-1d09c93331e6',
                    'geo_lat' => '59.9343',
                    'geo_lon' => '30.3351',
                ],
            ],
            [
                'value' => 'г Казань',
                'unrestricted_value' => '420111, Респ Татарстан, г Казань',
                'data' => [
                    'postal_code' => '420111',
                    'city' => 'Казань',
                    'city_with_type' => 'г Казань',
                    'city_fias_id' => '93b3df57-4e5e-4b4e-8b0e-0e0e0e0e0e0e',
                    'geo_lat' => '55.7887',
                    'geo_lon' => '49.1221',
                ],
            ],
            [
                'value' => 'г Краснодар',
                'unrestricted_value' => '350000, Краснодарский край, г Краснодар',
                'data' => [
                    'postal_code' => '350000',
                    'city' => 'Краснодар',
                    'city_with_type' => 'г Краснодар',
                    'city_fias_id' => 'f073f850-6c3b-4329-90ba-3c1489d457a1',
                    'geo_lat' => '45.0355',
                    'geo_lon' => '38.9753',
                ],
            ],
        ];

        $filtered = array_values(array_filter(
            $all,
            static fn (array $row): bool => $q === ''
                || str_contains(mb_strtolower($row['value']), $q)
                || str_contains(mb_strtolower((string) ($row['data']['city'] ?? '')), $q),
        ));

        return array_slice($filtered !== [] ? $filtered : $all, 0, $count);
    }
}
