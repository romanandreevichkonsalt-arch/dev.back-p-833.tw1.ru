<?php

namespace app\services\catalog;

final class CatalogRequestParams
{
    /** @var list<string> */
    public const MULTI_VALUE_FILTER_KEYS = ['color', 'texture'];

    public const DEFAULT_ITEMS_PER_GROUP = 3;
    public const MAX_ITEMS_PER_GROUP = 12;

    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function normalize(array $params, ?string $queryString = null): array
    {
        $params = $this->slugResolver->normalizeRequestParams($params);

        return self::applyMultiValueFilters(
            $params,
            $queryString ?? (string)(\Yii::$app->request->queryString ?? '')
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function applyMultiValueFilters(array $params, string $queryString): array
    {
        foreach (self::MULTI_VALUE_FILTER_KEYS as $key) {
            $fromQuery = self::parseMultiValueFromQueryString($queryString, $key);
            $merged = self::mergeFilterValues($params[$key] ?? null, $fromQuery);
            if ($merged !== []) {
                $params[$key] = $merged;
            } elseif (array_key_exists($key, $params)) {
                unset($params[$key]);
            }
        }

        return $params;
    }

    /**
     * @return list<string>
     */
    public static function parseMultiValueFromQueryString(string $queryString, string $name): array
    {
        $values = [];
        if ($queryString === '') {
            return $values;
        }

        foreach (explode('&', $queryString) as $segment) {
            if ($segment === '' || !str_contains($segment, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $segment, 2);
            $key = rawurldecode($rawKey);
            $value = rawurldecode($rawValue);
            if ($value === '') {
                continue;
            }

            $baseKey = preg_replace('/\[\]$/', '', $key) ?? $key;
            $baseKey = preg_replace('/\[\d+\]$/', '', $baseKey) ?? $baseKey;
            if ($baseKey !== $name) {
                continue;
            }

            foreach (self::expandFilterValue($value) as $token) {
                $values[] = $token;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<string>
     */
    public static function expandFilterValue(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/u', $value) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens !== [] ? $tokens : [$value];
    }

    /**
     * @return list<string>
     */
    public static function mergeFilterValues(mixed $fromParams, array $fromQuery): array
    {
        $values = [];

        if (is_array($fromParams)) {
            foreach ($fromParams as $item) {
                if (!is_string($item) && !is_numeric($item)) {
                    continue;
                }
                $values = array_merge($values, self::expandFilterValue((string)$item));
            }
        } elseif ($fromParams !== null && $fromParams !== '') {
            $values = array_merge($values, self::expandFilterValue((string)$fromParams));
        }

        $values = array_merge($values, $fromQuery);

        return array_values(array_unique(array_filter(
            array_map('strval', $values),
            static fn (string $v): bool => $v !== ''
        )));
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function isCollectionGroupsEnabled(array $params): bool
    {
        return self::parseBooleanParam($params['collectionGroups'] ?? null) === true;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function normalizeItemsPerGroup(array $params): int
    {
        if (!array_key_exists('itemsPerGroup', $params) || $params['itemsPerGroup'] === '' || $params['itemsPerGroup'] === null) {
            return self::DEFAULT_ITEMS_PER_GROUP;
        }

        $value = (int)$params['itemsPerGroup'];

        return min(self::MAX_ITEMS_PER_GROUP, max(1, $value));
    }

    public static function parseBooleanParam(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');

        if (in_array($normalized, ['0', 'false', 'no', 'нет'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'да'], true)) {
            return true;
        }

        return null;
    }
}
