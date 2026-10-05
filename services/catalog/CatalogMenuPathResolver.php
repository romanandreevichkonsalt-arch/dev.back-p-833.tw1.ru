<?php

namespace app\services\catalog;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

final class CatalogMenuPathResolver
{
    private const MAX_SEGMENTS = 4;

    /** @var list<string> */
    private const TYPE_PRIORITY = ['direction', 'modelLine', 'subcategory', 'category'];

    /** @var array<string, string> */
    private const TYPE_LABELS = [
        'direction' => 'направление',
        'modelLine' => 'коллекция',
        'category' => 'категория',
        'subcategory' => 'подкатегория',
    ];

    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * Разбирает path (`pryamoy-divan`, `artemida/pryamye`, `a-plus/divany/pryamye`)
     * в params для CatalogScope: direction, modelLine, category, subcategory.
     *
     * @return array<string, string>
     */
    public function toParams(string $path): array
    {
        $slugs = $this->splitPath($path);
        if ($slugs === []) {
            throw new NotFoundHttpException('Раздел каталога не найден.');
        }
        if (count($slugs) > self::MAX_SEGMENTS) {
            throw new BadRequestHttpException('В пути каталога не больше четырёх сегментов.');
        }

        $candidates = [];
        foreach ($slugs as $slug) {
            $types = $this->detectTypes($slug);
            if ($types === []) {
                throw new NotFoundHttpException('Раздел каталога не найден: ' . $slug);
            }
            $candidates[] = ['slug' => $slug, 'types' => $types];
        }

        $assigned = [
            'direction' => null,
            'modelLine' => null,
            'category' => null,
            'subcategory' => null,
        ];

        foreach ($candidates as $index => $item) {
            if (count($item['types']) !== 1) {
                continue;
            }
            $type = $item['types'][0];
            $this->assign($assigned, $type, $item['slug']);
            $candidates[$index]['types'] = [];
        }

        foreach ($candidates as $item) {
            if ($item['types'] === []) {
                continue;
            }
            $picked = null;
            foreach (self::TYPE_PRIORITY as $type) {
                if (in_array($type, $item['types'], true) && $assigned[$type] === null) {
                    $picked = $type;
                    break;
                }
            }
            if ($picked === null) {
                throw new BadRequestHttpException('Неоднозначный slug: ' . $item['slug']);
            }
            $this->assign($assigned, $picked, $item['slug']);
        }

        return array_filter($assigned, static fn (?string $value): bool => $value !== null);
    }

    /**
     * @return list<string>
     */
    public function splitPath(string $path): array
    {
        $slugs = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $slugs[] = $segment;
        }

        return $slugs;
    }

    /**
     * @return list<string>
     */
    private function detectTypes(string $slug): array
    {
        $types = [];
        if ($this->slugResolver->findDirectionByCollectionParam($slug) !== null) {
            $types[] = 'direction';
        }
        if ($this->slugResolver->findModelLineBySlug($slug) !== null) {
            $types[] = 'modelLine';
        }
        if ($this->slugResolver->findSubcategoryBySlug($slug) !== null) {
            $types[] = 'subcategory';
        }
        if ($this->slugResolver->findCategoryBySlug($slug) !== null) {
            $types[] = 'category';
        }

        return $types;
    }

    /**
     * @param array<string, string|null> $assigned
     */
    private function assign(array &$assigned, string $type, string $slug): void
    {
        if ($assigned[$type] !== null) {
            $label = self::TYPE_LABELS[$type] ?? $type;
            throw new BadRequestHttpException('В пути повторяется ' . $label . '.');
        }
        $assigned[$type] = $slug;
    }
}
