<?php

namespace app\services\search;

use app\models\User;
use app\services\catalog\CatalogService;

/**
 * Разбивка времени pipeline поиска (dev/bench).
 */
final class SearchBenchmarkRunner
{
    public function __construct(
        private readonly SearchService $search = new SearchService(),
        private readonly CatalogService $catalog = new CatalogService(),
        private readonly SearchQueryResolver $queryResolver = new SearchQueryResolver(),
        private readonly SearchTitleMatcher $titleMatcher = new SearchTitleMatcher(),
        private readonly SearchRanker $ranker = new SearchRanker(),
        private readonly SearchProductOrderingService $productOrdering = new SearchProductOrderingService(),
        private readonly SearchCategoriesFoundAggregator $categoriesFoundAggregator = new SearchCategoriesFoundAggregator(),
    ) {
    }

    /**
     * @return list<array{step: string, ms: float, meta: array<string, mixed>}>
     */
    public function profileSearch(string $query, int $limit = 5, ?User $dealer = null): array
    {
        $steps = [];
        $limit = max(1, min($limit, SearchRanker::MAX_PRODUCTS));

        $t0 = microtime(true);
        $bootstrap = $this->search->getMatchBootstrapForBench();
        $steps[] = $this->step('matchBootstrap', $t0, []);

        $t0 = microtime(true);
        $products = $this->catalog->getSearchableProducts();
        $steps[] = $this->step('searchableProducts', $t0, ['sku' => count($products)]);

        $frequent = $bootstrap['frequent'] ?? [];

        $t0 = microtime(true);
        $tokenVocabulary = $this->search->buildTokenVocabularyForBench($bootstrap, $products);
        $steps[] = $this->step('tokenVocabulary', $t0, ['terms' => count($tokenVocabulary)]);

        $t0 = microtime(true);
        $matchResult = $this->titleMatcher->match($products, $query, $tokenVocabulary);
        $matchedProducts = $matchResult['products'];
        $steps[] = $this->step('titleMatcher', $t0, ['matches' => count($matchedProducts)]);

        if ($matchedProducts === []) {
            $t0 = microtime(true);
            $matchedProducts = $this->search->matchProductsLegacyFallbackForBench($query, $bootstrap, $products);
            $steps[] = $this->step('rankerFallback', $t0, ['matches' => count($matchedProducts)]);
        }

        $t0 = microtime(true);
        $matchedProducts = $this->productOrdering->orderAll($matchedProducts);
        $steps[] = $this->step('orderAll', $t0, ['matches' => count($matchedProducts)]);

        $responseSlice = array_slice($matchedProducts, 0, $limit);

        $t0 = microtime(true);
        $categoriesFound = $this->categoriesFoundAggregator->aggregate($matchedProducts);
        $steps[] = $this->step('categoriesFound', $t0, ['groups' => count($categoriesFound)]);

        if ($dealer !== null && $dealer->isDealer()) {
            $t0 = microtime(true);
            $this->search->search($query, $limit, $dealer);
            $steps[] = $this->step('searchFullDealerOverlay', $t0, ['note' => 'full search() incl. pricing']);
        } else {
            $t0 = microtime(true);
            array_map(static fn (array $p): array => (new SearchDocumentBuilder())->toPublicProduct($p), $responseSlice);
            $steps[] = $this->step('toPublicProduct', $t0, ['count' => count($responseSlice)]);
        }

        return $steps;
    }

    /**
     * @param list<array{step: string, ms: float, meta: array<string, mixed>}> $steps
     */
    public function formatReport(string $query, array $steps): string
    {
        $total = array_sum(array_column($steps, 'ms'));
        $lines = [
            'Search bench: ' . $query,
            str_repeat('-', 56),
        ];
        foreach ($steps as $row) {
            $pct = $total > 0 ? round(100 * $row['ms'] / $total, 1) : 0;
            $meta = $row['meta'] !== [] ? ' ' . json_encode($row['meta'], JSON_UNESCAPED_UNICODE) : '';
            $lines[] = sprintf('  %-28s %7.2f ms (%5.1f%%)%s', $row['step'], $row['ms'], $pct, $meta);
        }
        $lines[] = str_repeat('-', 56);
        $lines[] = sprintf('  %-28s %7.2f ms', 'TOTAL (profiled)', $total);

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{step: string, ms: float, meta: array<string, mixed>}
     */
    private function step(string $name, float $startedAt, array $meta): array
    {
        return [
            'step' => $name,
            'ms' => (microtime(true) - $startedAt) * 1000,
            'meta' => $meta,
        ];
    }
}
