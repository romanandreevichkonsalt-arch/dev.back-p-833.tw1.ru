<?php

namespace app\services\favorites;

use app\exceptions\ApiValidationException;
use app\models\CatalogProduct;
use app\models\FavoriteItem;
use app\models\GuestSession;
use app\models\User;
use app\services\guest\ApiOwnerContext;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class FavoritesService
{
    /**
     * @return array{productId: string, isFavorite: bool}
     */
    public function add(ApiOwnerContext $owner, string $productSlug): array
    {
        $product = $this->findActiveProduct($productSlug);
        $this->upsertFavorite($owner, (int)$product->id);

        return [
            'productId' => (string)$product->slug,
            'isFavorite' => true,
        ];
    }

    /**
     * @return array{productId: string, isFavorite: bool}
     */
    public function remove(ApiOwnerContext $owner, string $productSlug): array
    {
        $productSlug = trim($productSlug);
        if ($productSlug === '') {
            throw new ApiValidationException('Ошибка валидации.', [
                'productId' => ['Поле productId обязательно.'],
            ]);
        }

        $product = CatalogProduct::find()->where(['slug' => $productSlug])->one();
        if ($product !== null) {
            FavoriteItem::deleteAll($this->ownerCondition($owner, (int)$product->id));
        }

        return [
            'productId' => $productSlug,
            'isFavorite' => false,
        ];
    }

    /**
     * @param list<string> $productSlugs
     * @return array{favorites: array<string, bool>}
     */
    public function check(ApiOwnerContext $owner, array $productSlugs): array
    {
        $slugs = [];
        foreach ($productSlugs as $slug) {
            $slug = trim((string)$slug);
            if ($slug === '' || isset($slugs[$slug])) {
                continue;
            }
            $slugs[$slug] = false;
        }

        if ($slugs === []) {
            return ['favorites' => []];
        }

        $products = CatalogProduct::find()
            ->select(['id', 'slug'])
            ->where(['slug' => array_keys($slugs)])
            ->indexBy('id')
            ->asArray()
            ->all();

        if ($products === []) {
            return ['favorites' => $slugs];
        }

        $ids = array_map('intval', array_keys($products));
        $favoritedIds = FavoriteItem::find()
            ->select('catalog_product_id')
            ->where($this->ownerWhere($owner))
            ->andWhere(['catalog_product_id' => $ids])
            ->column();

        foreach ($favoritedIds as $productId) {
            $slug = $products[(int)$productId]['slug'] ?? null;
            if ($slug !== null) {
                $slugs[$slug] = true;
            }
        }

        return ['favorites' => $slugs];
    }

    /**
     * @return array{page: int, pages: int, total: int, items: list<array{product: array<string, mixed>}>}
     */
    public function list(ApiOwnerContext $owner, int $page, int $pageSize, ?User $viewer = null): array
    {
        $page = max(1, $page);
        $pageSize = min(200, max(1, $pageSize));

        $query = FavoriteItem::find()
            ->where($this->ownerWhere($owner))
            ->with([
                'product.image',
                'product.badge',
                'product.fabricColor.swatchMedia',
                'product.fabricColor.colorImages.media',
                'product.catalogModel.fabricCollections.activeColors.catalogColor.swatchMedia',
                'product.catalogModel.fabricCollections.activeColors.swatchMedia',
                'product.catalogModel',
            ])
            ->orderBy(['id' => SORT_DESC]);

        $total = (int)$query->count();
        $pages = max(1, (int)ceil($total / $pageSize));
        if ($page > $pages) {
            return [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'items' => [],
            ];
        }

        /** @var FavoriteItem[] $items */
        $items = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();
        $dealer = ($viewer !== null && $viewer->isDealer()) ? $viewer : null;

        $resultItems = [];
        foreach ($items as $item) {
            if ($item->product === null) {
                continue;
            }
            $resultItems[] = [
                'product' => $item->product->toListingCard($dealer),
            ];
        }

        return [
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'items' => $resultItems,
        ];
    }

    /**
     * @return array{mergedCount: int, resultTotal: int}
     */
    public function sync(User $user, string $sessionId): array
    {
        $sessionId = (new \app\services\guest\GuestSessionService())->normalize($sessionId);
        if ($sessionId === '') {
            throw new BadRequestHttpException('Поле sessionId обязательно.');
        }

        $userId = (int)$user->id;
        $guest = GuestSession::findOne(['session_id' => $sessionId]);
        $hasGuestItems = FavoriteItem::find()->where(['session_id' => $sessionId])->exists();
        if ($guest !== null && $guest->favorites_merged_at !== null && !$hasGuestItems) {
            return [
                'mergedCount' => 0,
                'resultTotal' => (int)FavoriteItem::find()->where(['user_id' => $userId])->count(),
            ];
        }

        $guestItems = FavoriteItem::find()->where(['session_id' => $sessionId])->all();
        $merged = 0;

        foreach ($guestItems as $guestItem) {
            $exists = FavoriteItem::find()
                ->where(['user_id' => $userId, 'catalog_product_id' => $guestItem->catalog_product_id])
                ->exists();
            if ($exists) {
                $guestItem->delete();
                continue;
            }
            $guestItem->user_id = $userId;
            $guestItem->session_id = null;
            $guestItem->save(false);
            $merged++;
        }

        if ($guest !== null) {
            $guest->favorites_merged_at = date('Y-m-d H:i:s');
            $guest->save(false, ['favorites_merged_at', 'updated_at']);
        }

        return [
            'mergedCount' => $merged,
            'resultTotal' => (int)FavoriteItem::find()->where(['user_id' => $userId])->count(),
        ];
    }

    private function findActiveProduct(string $productSlug): CatalogProduct
    {
        $productSlug = trim($productSlug);
        if ($productSlug === '') {
            throw new ApiValidationException('Ошибка валидации.', [
                'productId' => ['Поле productId обязательно.'],
            ]);
        }

        $product = CatalogProduct::find()
            ->where(['slug' => $productSlug, 'is_active' => true])
            ->one();

        if ($product === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        return $product;
    }

    private function upsertFavorite(ApiOwnerContext $owner, int $productId): void
    {
        $existing = FavoriteItem::findOne($this->ownerCondition($owner, $productId));
        if ($existing !== null) {
            return;
        }

        $item = new FavoriteItem();
        $item->catalog_product_id = $productId;
        if ($owner->userId !== null) {
            $item->user_id = $owner->userId;
        } else {
            $item->session_id = $owner->sessionId;
        }
        $item->save(false);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerCondition(ApiOwnerContext $owner, ?int $productId = null): array
    {
        $cond = $this->ownerWhere($owner);
        if ($productId !== null) {
            $cond['catalog_product_id'] = $productId;
        }

        return $cond;
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerWhere(ApiOwnerContext $owner): array
    {
        if ($owner->userId !== null) {
            return ['user_id' => $owner->userId];
        }

        return ['session_id' => $owner->sessionId];
    }
}
