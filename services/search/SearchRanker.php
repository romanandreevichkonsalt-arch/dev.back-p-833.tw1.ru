<?php

namespace app\services\search;

class SearchRanker
{
    public const MAX_PRODUCTS = 20;
    public const MAX_SUGGESTIONS = 10;

    public function __construct(
        private readonly SearchQueryResolver $resolver = new SearchQueryResolver(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    public function matchProducts(
        array $products,
        string $effectiveQuery,
        string $rawQuery,
        ?int $limit = null,
    ): array
    {
        if (mb_strlen($rawQuery) < SearchQueryResolver::MIN_PRODUCT_QUERY_LENGTH) {
            return [];
        }

        $effectiveTokens = $this->splitQueryTokens($effectiveQuery);
        $scored = [];
        foreach ($products as $product) {
            $score = $this->scoreProduct($product, $effectiveQuery, $rawQuery, $effectiveTokens);
            if ($score <= 0) {
                continue;
            }
            $scored[] = ['score' => $score, 'product' => $product];
        }

        if ($scored === [] && $effectiveQuery !== $rawQuery) {
            $rawTokens = $this->splitQueryTokens($rawQuery);
            foreach ($products as $product) {
                $score = $this->scoreProduct($product, $rawQuery, $rawQuery, $rawTokens);
                if ($score <= 0) {
                    continue;
                }
                $scored[] = ['score' => $score, 'product' => $product];
            }
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
                ?: strcmp((string)($left['product']['title'] ?? ''), (string)($right['product']['title'] ?? ''))
        );

        $matched = array_map(
            static fn (array $row): array => $row['product'],
            $scored
        );

        if ($limit === null) {
            return $matched;
        }

        return array_slice($matched, 0, max(1, $limit));
    }

    /**
     * @param list<array<string, mixed>> $categories
     *
     * @return list<array<string, mixed>>
     */
    public function matchCategories(array $categories, string $effectiveQuery, string $rawQuery): array
    {
        if (mb_strlen($rawQuery) < SearchQueryResolver::MIN_SUGGESTION_QUERY_LENGTH) {
            return [];
        }

        $matched = [];
        foreach ($categories as $category) {
            $label = $this->resolver->normalize((string)($category['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if ($this->matchesCategory($label, $effectiveQuery) || $this->matchesCategory($label, $rawQuery)) {
                $matched[] = $category;
            }
        }

        return $matched;
    }

    /**
     * @param list<string> $frequentQueries
     * @param list<string> $vocabulary
     *
     * @return list<string>
     */
    public function matchSuggestions(
        array $frequentQueries,
        array $vocabulary,
        string $effectiveQuery,
        string $rawQuery,
        ?string $correction,
    ): array {
        if (mb_strlen($rawQuery) < SearchQueryResolver::MIN_SUGGESTION_QUERY_LENGTH) {
            return [];
        }

        $suggestions = [];
        foreach ($frequentQueries as $item) {
            $normalized = $this->resolver->normalize($item);
            if ($this->matchesSuggestion($normalized, $rawQuery, $effectiveQuery)) {
                $suggestions[$normalized] = $item;
            }
        }

        foreach ($vocabulary as $term) {
            if ($this->matchesSuggestion($term, $rawQuery, $effectiveQuery)) {
                $suggestions[$term] = $term;
            }
        }

        if ($correction !== null) {
            $suggestions[$correction] = $correction;
        }

        return array_slice(array_values($suggestions), 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @param array<string, mixed> $product
     * @param list<string> $effectiveTokens
     */
    private function scoreProduct(array $product, string $effectiveQuery, string $rawQuery, array $effectiveTokens): int
    {
        $title = (string)($product['_titleNormalized'] ?? $this->resolver->normalize((string)($product['title'] ?? '')));
        if (isset($product['_searchHaystackNormalized']) && is_string($product['_searchHaystackNormalized'])) {
            $haystack = $product['_searchHaystackNormalized'];
        } else {
            $haystackSource = (string)($product['_searchText'] ?? implode(' ', array_filter([
                $product['title'] ?? '',
                $product['subtitle'] ?? '',
                $product['description'] ?? '',
                $product['type'] ?? '',
                $product['subcategoryLabel'] ?? '',
                $product['collection'] ?? '',
                $product['fabricColorLabel'] ?? '',
            ], static fn ($part): bool => trim((string)$part) !== '')));
            $haystack = $this->resolver->normalize($haystackSource);
        }

        if (!$this->mightScore($title, $haystack, $effectiveQuery, $rawQuery, $effectiveTokens)) {
            return 0;
        }

        $score = 0;
        $score += $this->scoreContains($title, $effectiveQuery, 120, 60);
        $score += $this->scoreContains($haystack, $effectiveQuery, 80, 30);

        if ($rawQuery !== $effectiveQuery) {
            $score += $this->scoreContains($title, $rawQuery, 50, 20);
            $score += $this->scoreContains($haystack, $rawQuery, 30, 10);
        }

        foreach ($effectiveTokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 15;
            }
        }

        return $score;
    }

    /**
     * @param list<string> $effectiveTokens
     */
    private function mightScore(
        string $title,
        string $haystack,
        string $effectiveQuery,
        string $rawQuery,
        array $effectiveTokens,
    ): bool {
        if ($this->containsQueryFragment($title, $haystack, $effectiveQuery)) {
            return true;
        }

        foreach ($effectiveTokens as $token) {
            if ($token !== '' && (str_contains($title, $token) || str_contains($haystack, $token))) {
                return true;
            }
        }

        if ($rawQuery === $effectiveQuery) {
            return false;
        }

        return $this->containsQueryFragment($title, $haystack, $rawQuery);
    }

    private function containsQueryFragment(string $title, string $haystack, string $query): bool
    {
        if ($query === '') {
            return false;
        }

        return str_contains($title, $query) || str_contains($haystack, $query);
    }

    private function scoreContains(string $haystack, string $needle, int $containsScore, int $prefixScore): int
    {
        if ($needle === '' || !str_contains($haystack, $needle)) {
            return 0;
        }

        return str_starts_with($haystack, $needle) ? $prefixScore + $containsScore : $containsScore;
    }

    private function matchesCategory(string $label, string $query): bool
    {
        if ($query === '') {
            return false;
        }

        return str_contains($label, $query) || str_starts_with($label, $query);
    }

    private function matchesSuggestion(string $candidate, string $rawQuery, string $effectiveQuery): bool
    {
        return str_starts_with($candidate, $rawQuery)
            || str_starts_with($candidate, $effectiveQuery)
            || str_contains($candidate, $rawQuery);
    }

    /**
     * @return list<string>
     */
    private function splitQueryTokens(string $query): array
    {
        $parts = preg_split('/\s+/u', $this->resolver->normalize($query)) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => mb_strlen($part) >= SearchQueryResolver::MIN_PRODUCT_QUERY_LENGTH
        ));
    }
}
