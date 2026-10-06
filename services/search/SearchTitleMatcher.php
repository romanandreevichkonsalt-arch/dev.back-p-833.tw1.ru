<?php

namespace app\services\search;

/**
 * Pipeline: normalize → token-fix → exact title/slug → cascade → similar.
 */
class SearchTitleMatcher
{
    public const MATCH_EXACT = 'exact';
    public const MATCH_CASCADE = 'cascade';
    public const MATCH_SIMILAR = 'similar';
    public const MATCH_NONE = 'none';

    public const BROAD_MATCH_THRESHOLD = 50;

    public function __construct(
        private readonly SearchQueryResolver $resolver = new SearchQueryResolver(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param list<string> $tokenVocabulary
     *
     * @return array{
     *     products: list<array<string, mixed>>,
     *     matchType: string,
     *     matchedQuery: string|null,
     *     correction: string|null,
     *     raw: string
     * }
     */
    public function match(array $products, string $query, array $tokenVocabulary): array
    {
        $raw = $this->resolver->normalize($query);
        if (mb_strlen($raw) < SearchQueryResolver::MIN_PRODUCT_QUERY_LENGTH) {
            return $this->emptyResult($raw);
        }

        $tokenFix = $this->stageTokenFix($raw, $tokenVocabulary);
        $searchQuery = $tokenFix['query'];
        $correction = $tokenFix['correction'];

        $exact = $this->stageExact($products, $searchQuery);
        if ($exact !== []) {
            return $this->buildResult($exact, self::MATCH_EXACT, $searchQuery, $raw, $correction);
        }

        $wordCount = $this->countWords($searchQuery);
        $cascade = $this->stageCascade($products, $searchQuery, $wordCount);
        if ($cascade !== null) {
            $cascadeCorrection = $correction;
            if ($cascade['matchedQuery'] !== $searchQuery && $cascade['matchedQuery'] !== $raw) {
                $cascadeCorrection = $cascade['matchedQuery'];
            }

            return $this->buildResult(
                $cascade['products'],
                self::MATCH_CASCADE,
                $cascade['matchedQuery'],
                $raw,
                $cascadeCorrection
            );
        }

        $similar = $this->stageSimilar($products, $searchQuery);
        if ($similar !== []) {
            return $this->buildResult($similar, self::MATCH_SIMILAR, $searchQuery, $raw, $correction);
        }

        if ($searchQuery !== $raw) {
            $similarRaw = $this->stageSimilar($products, $raw);
            if ($similarRaw !== []) {
                return $this->buildResult($similarRaw, self::MATCH_SIMILAR, $raw, $raw, null);
            }
        }

        return $this->emptyResult($raw);
    }

    public function shouldUseRoundRobin(string $matchType, ?string $matchedQuery): bool
    {
        if ($matchType === self::MATCH_EXACT) {
            return false;
        }

        if ($matchType === self::MATCH_CASCADE && $matchedQuery !== null) {
            return $this->countWords($matchedQuery) < 4;
        }

        return $matchType === self::MATCH_SIMILAR;
    }

    /**
     * @param list<string> $tokenVocabulary
     *
     * @return array{query: string, correction: string|null}
     */
    private function stageTokenFix(string $raw, array $tokenVocabulary): array
    {
        $tokenFix = $this->resolver->fixTokensInQuery($raw, $tokenVocabulary);

        return [
            'query' => $tokenFix['fixed'],
            'correction' => $tokenFix['changed'] ? $tokenFix['fixed'] : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function stageExact(array $products, string $searchQuery): array
    {
        $byTitle = $this->filterByTitleExact($products, $searchQuery);
        if ($byTitle !== []) {
            return $byTitle;
        }

        return $this->filterBySlugExact($products, $searchQuery);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return array{products: list<array<string, mixed>>, matchedQuery: string}|null
     */
    private function stageCascade(array $products, string $searchQuery, int $originalWordCount): ?array
    {
        return $this->findCascade($products, $searchQuery, $originalWordCount);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function stageSimilar(array $products, string $searchQuery): array
    {
        return $this->findSimilar($products, $searchQuery);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function filterByTitleExact(array $products, string $normalizedQuery): array
    {
        $matches = [];
        foreach ($products as $product) {
            if ($this->normalizedTitle($product) === $normalizedQuery) {
                $matches[] = $product;
            }
        }

        return $this->sortByTitleLength($matches);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function filterBySlugExact(array $products, string $normalizedQuery): array
    {
        $slugCandidate = $this->toSlugCandidate($normalizedQuery);
        if ($slugCandidate === '') {
            return [];
        }

        $matches = [];
        foreach ($products as $product) {
            $slug = strtolower((string)($product['slug'] ?? $product['id'] ?? ''));
            if ($slug === $slugCandidate || $slug === $normalizedQuery) {
                $matches[] = $product;
            }
        }

        return $this->sortByTitleLength($matches);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function filterByTitlePrefix(array $products, string $normalizedVariant): array
    {
        $matches = [];
        foreach ($products as $product) {
            $title = $this->normalizedTitle($product);
            if ($title === $normalizedVariant || str_starts_with($title, $normalizedVariant . ' ')) {
                $matches[] = $product;
            }
        }

        return $this->sortByTitleLength($matches);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function filterByTitleContains(array $products, string $normalizedVariant): array
    {
        $matches = [];
        foreach ($products as $product) {
            $title = $this->normalizedTitle($product);
            if (str_contains($title, $normalizedVariant)) {
                $matches[] = $product;
            }
        }

        return $this->sortByTitleLength($matches);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return array{products: list<array<string, mixed>>, matchedQuery: string}|null
     */
    private function findCascade(array $products, string $query, int $originalWordCount): ?array
    {
        $tokens = preg_split('/\s+/u', trim($query)) ?: [];
        $tokenCount = count($tokens);

        for ($len = $tokenCount; $len >= 1; $len--) {
            $variant = implode(' ', array_slice($tokens, 0, $len));
            if (mb_strlen($variant) < SearchQueryResolver::MIN_PRODUCT_QUERY_LENGTH) {
                continue;
            }

            if ($len < $tokenCount) {
                $exact = $this->filterByTitleExact($products, $variant);
                if ($exact !== []) {
                    return ['products' => $exact, 'matchedQuery' => $variant];
                }
            }

            $prefix = $this->filterByTitlePrefix($products, $variant);
            if ($prefix !== [] && !$this->isTooBroad($len, $originalWordCount, $prefix)) {
                return ['products' => $prefix, 'matchedQuery' => $variant];
            }

            if ($len >= 2 || $originalWordCount === 1) {
                $contains = $this->filterByTitleContains($products, $variant);
                if ($contains !== [] && !$this->isTooBroad($len, $originalWordCount, $contains)) {
                    return ['products' => $contains, 'matchedQuery' => $variant];
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function findSimilar(array $products, string $query): array
    {
        $maxDistance = $this->maxTitleDistance(mb_strlen($query));
        $scored = [];

        $queryLength = mb_strlen($query);

        foreach ($products as $product) {
            $title = $this->normalizedTitle($product);
            if ($title === '') {
                continue;
            }

            if (abs(mb_strlen($title) - $queryLength) > $maxDistance) {
                continue;
            }

            $distance = $this->resolver->levenshteinMb($query, $title);
            if ($distance > $maxDistance) {
                continue;
            }

            $scored[] = ['distance' => $distance, 'product' => $product];
        }

        if ($scored === []) {
            return [];
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => $left['distance'] <=> $right['distance']
                ?: mb_strlen((string)($left['product']['title'] ?? '')) <=> mb_strlen((string)($right['product']['title'] ?? ''))
        );

        return array_map(static fn (array $row): array => $row['product'], $scored);
    }

    /**
     * @param list<array<string, mixed>> $matches
     */
    private function isTooBroad(int $variantWordCount, int $originalWordCount, array $matches): bool
    {
        return $variantWordCount === 1
            && $originalWordCount >= 2
            && count($matches) > self::BROAD_MATCH_THRESHOLD;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function normalizedTitle(array $product): string
    {
        $cached = $product['_titleNormalized'] ?? null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->resolver->normalize((string)($product['title'] ?? $product['name'] ?? ''));
    }

    private function toSlugCandidate(string $normalizedQuery): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalizedQuery) === 1) {
            return $normalizedQuery;
        }

        return str_replace(' ', '-', $normalizedQuery);
    }

    private function countWords(string $query): int
    {
        return count(preg_split('/\s+/u', trim($query)) ?: []);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function sortByTitleLength(array $products): array
    {
        usort(
            $products,
            static fn (array $left, array $right): int => mb_strlen((string)($left['title'] ?? ''))
                <=> mb_strlen((string)($right['title'] ?? ''))
        );

        return $products;
    }

    private function maxTitleDistance(int $queryLength): int
    {
        if ($queryLength >= 30) {
            return 3;
        }

        if ($queryLength >= 15) {
            return 2;
        }

        return 1;
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return array{
     *     products: list<array<string, mixed>>,
     *     matchType: string,
     *     matchedQuery: string|null,
     *     correction: string|null,
     *     raw: string
     * }
     */
    private function buildResult(
        array $products,
        string $matchType,
        ?string $matchedQuery,
        string $raw,
        ?string $correction,
    ): array {
        if ($correction === $raw) {
            $correction = null;
        }

        return [
            'products' => $products,
            'matchType' => $matchType,
            'matchedQuery' => $matchedQuery,
            'correction' => $correction,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{
     *     products: list<array<string, mixed>>,
     *     matchType: string,
     *     matchedQuery: string|null,
     *     correction: string|null,
     *     raw: string
     * }
     */
    private function emptyResult(string $raw): array
    {
        return [
            'products' => [],
            'matchType' => self::MATCH_NONE,
            'matchedQuery' => null,
            'correction' => null,
            'raw' => $raw,
        ];
    }
}
