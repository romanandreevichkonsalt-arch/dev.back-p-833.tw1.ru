<?php

namespace app\services\search;

class SearchOftenSearchedMatcher
{
    public const MAX_ITEMS = 10;

    public function __construct(
        private readonly SearchQueryResolver $resolver = new SearchQueryResolver(),
        private readonly SearchCatalogVocabulary $vocabulary = new SearchCatalogVocabulary(),
    ) {
    }

    /**
     * @param list<string> $staticFrequent
     *
     * @return list<array{label: string, href: string|null}>
     */
    public function match(string $query, array $staticFrequent = [], int $limit = self::MAX_ITEMS): array
    {
        return $this->matchAgainst(
            $query,
            $this->vocabulary->getSubcategories(),
            $this->vocabulary->getCollections(),
            $staticFrequent,
            $limit
        );
    }

    /**
     * @param list<array{id: string, label: string, href: string}> $subcategories
     * @param list<array{id: string, label: string, href: string}> $collections
     * @param list<string> $staticFrequent
     *
     * @return list<array{label: string, href: string|null}>
     */
    public function matchAgainst(
        string $query,
        array $subcategories,
        array $collections,
        array $staticFrequent = [],
        int $limit = self::MAX_ITEMS,
    ): array {
        if (mb_strlen(trim($query)) < SearchQueryResolver::MIN_PRODUCT_QUERY_LENGTH) {
            return [];
        }

        $normalizedQuery = $this->resolver->normalize($query);
        $items = [];

        foreach ($subcategories as $subcategory) {
            $this->pushMatch($items, (string)$subcategory['label'], (string)$subcategory['href'], 100, $normalizedQuery);
        }

        foreach ($collections as $collection) {
            $this->pushMatch($items, (string)$collection['label'], (string)$collection['href'], 50, $normalizedQuery);
        }

        foreach ($staticFrequent as $frequentQuery) {
            $label = trim((string)$frequentQuery);
            if ($label === '') {
                continue;
            }
            $this->pushMatch($items, $label, null, 10, $normalizedQuery);
        }

        usort(
            $items,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
                ?: mb_strlen($left['label']) <=> mb_strlen($right['label'])
        );

        $result = [];
        $seen = [];
        foreach ($items as $item) {
            $key = $this->resolver->normalize($item['label']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = [
                'label' => $item['label'],
                'href' => $item['href'],
            ];
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param list<array{label: string, href: string|null, score: int}> $items
     */
    private function pushMatch(array &$items, string $label, ?string $href, int $baseScore, string $normalizedQuery): void
    {
        $normalizedLabel = $this->resolver->normalize($label);
        if ($normalizedLabel === '') {
            return;
        }

        $score = $this->scoreMatch($normalizedLabel, $normalizedQuery, $baseScore);
        if ($score <= 0) {
            return;
        }

        $items[] = [
            'label' => $label,
            'href' => $href,
            'score' => $score,
        ];
    }

    private function scoreMatch(string $normalizedLabel, string $normalizedQuery, int $baseScore): int
    {
        if ($normalizedQuery === '') {
            return 0;
        }

        if ($normalizedLabel === $normalizedQuery) {
            return $baseScore + 40;
        }

        if (str_starts_with($normalizedLabel, $normalizedQuery)) {
            return $baseScore + 30;
        }

        if (str_contains($normalizedLabel, $normalizedQuery)) {
            return $baseScore + 10;
        }

        return 0;
    }
}
