<?php

namespace app\services\import\surface;

use app\helpers\SlugHelper;
use app\models\CatalogCollection;

class AppliedCatalogCollectionResolver
{
    /** @var array<string, int|null> */
    private array $cache = [];

    /**
     * @return array{matched: int[], unmatched: string[]}
     */
    public function resolveFromAppliedModelsText(?string $text): array
    {
        $matched = [];
        $unmatched = [];

        foreach ($this->parseTokens($text) as $token) {
            $collectionId = $this->findCollectionId($token);
            if ($collectionId !== null) {
                $matched[] = $collectionId;
            } else {
                $unmatched[] = $token;
            }
        }

        return [
            'matched' => array_values(array_unique($matched)),
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @return string[]
     */
    public function parseTokens(?string $text): array
    {
        $text = trim((string)$text);
        if ($text === '') {
            return [];
        }

        $normalized = preg_replace('/\s+и\s+/u', ',', $text) ?? $text;
        $parts = preg_split('/[,;\n\r]+/u', $normalized) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            if ($part === '' || mb_strtolower($part) === '№') {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    private function findCollectionId(string $token): ?int
    {
        $cacheKey = mb_strtolower($token);
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $collection = CatalogCollection::find()
            ->where(['name' => $token])
            ->one();
        if ($collection === null) {
            $collection = CatalogCollection::find()
                ->where(['slug' => SlugHelper::slugify($token)])
                ->one();
        }
        $id = $collection !== null ? (int)$collection->id : null;
        $this->cache[$cacheKey] = $id;

        return $id;
    }
}
