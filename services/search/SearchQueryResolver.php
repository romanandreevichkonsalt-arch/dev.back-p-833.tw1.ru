<?php

namespace app\services\search;

class SearchQueryResolver
{
    public const MIN_PRODUCT_QUERY_LENGTH = 3;
    public const MIN_SUGGESTION_QUERY_LENGTH = 3;

    public function normalize(string $query): string
    {
        $query = mb_strtolower(trim($query));
        $query = str_replace('ё', 'е', $query);
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return $query;
    }

    /**
     * @param list<string> $vocabulary
     *
     * @return array{
     *     raw: string,
     *     effective: string,
     *     correction: string|null,
     *     mode: 'empty'|'exact'|'prefix'|'corrected'|'literal'
     * }
     */
    public function resolve(string $query, array $vocabulary): array
    {
        $raw = $this->normalize($query);
        if ($raw === '') {
            return [
                'raw' => '',
                'effective' => '',
                'correction' => null,
                'mode' => 'empty',
            ];
        }

        $vocabulary = $this->normalizeVocabulary($vocabulary);
        if (in_array($raw, $vocabulary, true)) {
            return [
                'raw' => $raw,
                'effective' => $raw,
                'correction' => null,
                'mode' => 'exact',
            ];
        }

        $prefixMatch = $this->resolvePrefix($raw, $vocabulary);
        if ($prefixMatch !== null) {
            return [
                'raw' => $raw,
                'effective' => $prefixMatch,
                'correction' => $prefixMatch,
                'mode' => 'prefix',
            ];
        }

        if (mb_strlen($raw) >= 4) {
            $typoMatch = $this->resolveTypo($raw, $vocabulary);
            if ($typoMatch !== null) {
                return [
                    'raw' => $raw,
                    'effective' => $typoMatch,
                    'correction' => $typoMatch,
                    'mode' => 'corrected',
                ];
            }
        }

        return [
            'raw' => $raw,
            'effective' => $raw,
            'correction' => null,
            'mode' => 'literal',
        ];
    }

    /**
     * @param list<string> $vocabulary
     *
     * @return list<string>
     */
    public function buildVocabulary(array $frequentQueries, array $categories, array $products): array
    {
        $terms = [
            'диван',
            'кресло',
            'матрас',
            'стол',
            'шкаф',
            'тумба',
            'комод',
            'пуф',
            'стеллаж',
        ];

        $terms = array_merge(
            $terms,
            $this->collectTermsFromFrequentAndCategories($frequentQueries, $categories),
            $this->collectProductTerms($products, includeSearchText: true),
        );

        return $this->normalizeVocabulary($terms);
    }

    /**
     * @param list<string> $frequentQueries
     * @param list<array<string, mixed>> $categories
     * @return list<string>
     */
    private function collectTermsFromFrequentAndCategories(array $frequentQueries, array $categories): array
    {
        $termSet = [];

        foreach ($frequentQueries as $query) {
            $this->addTermsToSet($termSet, (string)$query);
        }

        foreach ($categories as $category) {
            $this->addTermsToSet($termSet, (string)($category['label'] ?? ''));
        }

        return array_keys($termSet);
    }

    /**
     * @param array<string, true> $termSet
     */
    private function addTermsToSet(array &$termSet, string $text): void
    {
        $normalized = $this->normalize($text);
        if ($normalized !== '') {
            $termSet[$normalized] = true;
        }

        foreach ($this->splitTerms($text) as $term) {
            $normalizedTerm = $this->normalize($term);
            if ($normalizedTerm !== '') {
                $termSet[$normalizedTerm] = true;
            }
        }
    }

    /**
     * Словарь для token-fix (без полного _searchText — только слова и короткие фразы).
     *
     * @param list<string> $frequentQueries
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    public function buildTokenVocabulary(array $frequentQueries, array $categories, array $products): array
    {
        $terms = [
            'диван',
            'кресло',
            'матрас',
            'стол',
            'шкаф',
            'тумба',
            'комод',
            'пуф',
            'стеллаж',
        ];

        $terms = array_merge(
            $terms,
            $this->collectTermsFromFrequentAndCategories($frequentQueries, $categories),
            $this->collectProductTerms($products, includeSearchText: false),
        );

        return $this->normalizeVocabulary($terms);
    }

    /**
     * @param list<string> $vocabulary
     *
     * @return array{fixed: string, changed: bool}
     */
    public function fixTokensInQuery(string $normalizedQuery, array $vocabulary): array
    {
        $parts = preg_split('/\s+/u', trim($normalizedQuery)) ?: [];
        $fixed = [];
        $changed = false;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (mb_strlen($part) < self::MIN_SUGGESTION_QUERY_LENGTH || in_array($part, $vocabulary, true)) {
                $fixed[] = $part;

                continue;
            }

            $closest = $this->findClosestTerm($part, $vocabulary);
            if ($closest !== null && $closest !== $part) {
                $fixed[] = $closest;
                $changed = true;

                continue;
            }

            $fixed[] = $part;
        }

        return [
            'fixed' => implode(' ', $fixed),
            'changed' => $changed,
        ];
    }

    public function levenshteinMb(string $left, string $right): int
    {
        return $this->levenshteinMbInternal($left, $right);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    private function collectProductTerms(array $products, bool $includeSearchText = true): array
    {
        $termSet = [];

        foreach ($products as $product) {
            $this->addTermsToSet($termSet, (string)($product['title'] ?? ''));

            foreach ([
                (string)($product['type'] ?? ''),
                (string)($product['collection'] ?? ''),
                (string)($product['fabricColorLabel'] ?? ''),
                (string)($product['subcategoryLabel'] ?? ''),
            ] as $field) {
                $this->addTermsToSet($termSet, $field);
            }

            if ($includeSearchText && !empty($product['_searchHaystackNormalized']) && is_string($product['_searchHaystackNormalized'])) {
                $this->addTermsToSet($termSet, $product['_searchHaystackNormalized']);
            }
        }

        return array_keys($termSet);
    }

    /**
     * @param list<string> $vocabulary
     */
    private function findClosestTerm(string $token, array $vocabulary): ?string
    {
        $bestTerm = null;
        $bestDistance = PHP_INT_MAX;
        $tokenLength = mb_strlen($token);
        $maxDistance = $tokenLength >= 6 ? 2 : 1;

        foreach ($vocabulary as $term) {
            $termLength = mb_strlen($term);
            if (abs($termLength - $tokenLength) > 2) {
                continue;
            }

            $distance = $this->levenshteinMbInternal($token, $term);
            if ($distance > $maxDistance || $distance >= $bestDistance) {
                continue;
            }

            $bestDistance = $distance;
            $bestTerm = $term;
        }

        return $bestDistance <= $maxDistance ? $bestTerm : null;
    }

    /**
     * @param list<string> $vocabulary
     *
     * @return list<string>
     */
    private function normalizeVocabulary(array $vocabulary): array
    {
        $normalized = [];
        foreach ($vocabulary as $term) {
            $term = $this->normalize((string)$term);
            if ($term === '' || mb_strlen($term) < 2) {
                continue;
            }
            $normalized[$term] = $term;
        }

        return array_values($normalized);
    }

    /**
     * @return list<string>
     */
    private function splitTerms(string $value): array
    {
        $parts = preg_split('/[\s\-\/]+/u', mb_strtolower($value)) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => mb_strlen($part) >= 3
        ));
    }

    /**
     * @param list<string> $vocabulary
     */
    private function resolvePrefix(string $raw, array $vocabulary): ?string
    {
        if (mb_strlen($raw) < self::MIN_SUGGESTION_QUERY_LENGTH) {
            return null;
        }

        $matches = array_values(array_filter(
            $vocabulary,
            static fn (string $term): bool => str_starts_with($term, $raw) && $term !== $raw
        ));
        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => mb_strlen($a) <=> mb_strlen($b));

        $eligible = array_values(array_filter(
            $matches,
            static fn (string $term): bool => mb_strlen($term) >= mb_strlen($raw) + 2
        ));
        if ($eligible !== []) {
            return $eligible[0];
        }

        if (count($matches) === 1) {
            return null;
        }

        // «Мёртвая зона»: короткий префикс с несколькими кандидатами не расширяем (тол ≠ стол).
        if (mb_strlen($raw) <= 3) {
            return null;
        }

        return $matches[0];
    }

    /**
     * @param list<string> $vocabulary
     */
    private function resolveTypo(string $raw, array $vocabulary): ?string
    {
        $bestTerm = null;
        $bestDistance = PHP_INT_MAX;
        $rawLength = mb_strlen($raw);
        $maxDistance = $rawLength >= 6 ? 2 : ($rawLength >= 4 ? 2 : 1);

        foreach ($vocabulary as $term) {
            $termLength = mb_strlen($term);
            if (abs($termLength - $rawLength) > 2) {
                continue;
            }

            $distance = $this->levenshteinMbInternal($raw, $term);
            if ($distance > $maxDistance || $distance >= $bestDistance) {
                continue;
            }

            $bestDistance = $distance;
            $bestTerm = $term;
        }

        return $bestDistance <= $maxDistance ? $bestTerm : null;
    }

    private function levenshteinMbInternal(string $left, string $right): int
    {
        $leftChars = $this->splitChars($left);
        $rightChars = $this->splitChars($right);
        $leftCount = count($leftChars);
        $rightCount = count($rightChars);

        if ($leftCount === 0) {
            return $rightCount;
        }
        if ($rightCount === 0) {
            return $leftCount;
        }

        $matrix = [];
        for ($i = 0; $i <= $leftCount; $i++) {
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $rightCount; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $leftCount; $i++) {
            for ($j = 1; $j <= $rightCount; $j++) {
                $cost = $leftChars[$i - 1] === $rightChars[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        return $matrix[$leftCount][$rightCount];
    }

    /**
     * @return list<string>
     */
    private function splitChars(string $value): array
    {
        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
