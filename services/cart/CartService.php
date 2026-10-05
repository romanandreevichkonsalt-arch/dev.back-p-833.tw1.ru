<?php

namespace app\services\cart;

use app\exceptions\ApiValidationException;
use app\models\CartItem;
use app\models\CatalogProduct;
use app\models\GuestSession;
use app\models\User;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionPricing;
use app\services\promotion\CatalogPromotionResolver;
use app\services\guest\ApiOwnerContext;
use app\services\guest\GuestSessionService;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class CartService
{
    public function __construct(
        private readonly CartAttachmentUploadService $attachmentUploadService = new CartAttachmentUploadService(),
    ) {
    }
    /**
     * @return array{items: list<array<string, mixed>>, totalQuantity: int, subtotal: float, promoDiscount: float, cashbackApplied: float, total: float, promo: ?array<string, mixed>, cashbackAvailable: float, cashbackMaxApplicable: float}
     */
    public function getCartPayload(ApiOwnerContext $owner, ?User $viewer = null): array
    {
        $items = $this->findItems($owner);
        $lines = [];
        $totalQuantity = 0;

        foreach ($items as $item) {
            if ($item->product === null) {
                continue;
            }
            $line = $this->buildLineFromItem($item, $this->resolveDealerViewer($viewer));
            $lines[] = $line;
            $totalQuantity += (int)$item->quantity;
        }

        if ($owner->userId !== null) {
            return (new CartCheckoutService())->enrichPayload((int)$owner->userId, $lines, $totalQuantity, $viewer);
        }

        return (new CartCheckoutService())->enrichGuestPayload($lines, $totalQuantity);
    }

    /**
     * @return array<string, mixed>
     */
    public function addItem(ApiOwnerContext $owner, string $productSlug, int $quantity = 1): array
    {
        $product = $this->findActiveProduct($productSlug);
        if ($quantity < 1) {
            throw new ApiValidationException('Ошибка валидации.', [
                'quantity' => ['Количество должно быть не меньше 1.'],
            ]);
        }

        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));

        if ($item === null) {
            $item = new CartItem([
                'catalog_product_id' => (int)$product->id,
                'quantity' => $quantity,
            ]);
            $this->assignOwner($item, $owner);
        } else {
            $item->quantity = (int)$item->quantity + $quantity;
        }

        if (!$item->save()) {
            throw new ApiValidationException('Не удалось добавить товар в корзину.', $item->getErrors());
        }

        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadItemAttachment(ApiOwnerContext $owner, User $user, string $productSlug, UploadedFile $file): array
    {
        if (!$user->isDealer()) {
            throw new ForbiddenHttpException('Файлы к позициям доступны только дилерам.');
        }

        $product = $this->findActiveProduct($productSlug);
        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item === null) {
            throw new NotFoundHttpException('Товар не найден в корзине.');
        }

        $saved = $this->attachmentUploadService->saveForCartItem($item, $productSlug, $file);
        $this->attachmentUploadService->deleteStoredFile($item->attachment_path);
        $item->attachment_path = $saved['path'];
        $item->attachment_original_name = $saved['originalName'];
        if (!$item->save()) {
            $this->attachmentUploadService->deleteStoredFile($saved['path']);
            throw new ApiValidationException('Не удалось сохранить файл.', $item->getErrors());
        }

        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeItemAttachment(ApiOwnerContext $owner, User $user, string $productSlug): array
    {
        if (!$user->isDealer()) {
            throw new ForbiddenHttpException('Файлы к позициям доступны только дилерам.');
        }

        $product = $this->findActiveProduct($productSlug);
        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item === null) {
            throw new NotFoundHttpException('Товар не найден в корзине.');
        }

        $this->attachmentUploadService->deleteStoredFile($item->attachment_path);
        $item->attachment_path = null;
        $item->attachment_original_name = null;
        if (!$item->save()) {
            throw new ApiValidationException('Не удалось удалить файл.', $item->getErrors());
        }

        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item);
    }

    public function findItemAttachment(ApiOwnerContext $owner, User $user, string $productSlug): CartItem
    {
        if (!$user->isDealer()) {
            throw new ForbiddenHttpException('Файлы к позициям доступны только дилерам.');
        }

        $product = $this->findActiveProduct($productSlug);
        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item === null) {
            throw new NotFoundHttpException('Товар не найден в корзине.');
        }

        if ($item->attachment_path === null || trim($item->attachment_path) === '') {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLineFromCartItem(CartItem $item, ?User $dealer = null): array
    {
        return $this->buildLineFromItem($item, $dealer);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLineFromItem(CartItem $item, ?User $dealer = null): array
    {
        $product = $item->product;
        if ($product === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        $dealer ??= $this->resolveDealerForItem($item);
        $pricingService = new DealerPricingService();
        $line = $product->toCartLineApiItem((int)$item->quantity, $dealer);
        $quantity = (int)$item->quantity;
        $catalogRetailUnit = $line['retailPrice'] ?? null;
        $retailLineTotal = $catalogRetailUnit !== null
            ? round((float)$catalogRetailUnit * $quantity, 2)
            : null;

        $unitPrice = $pricingService->resolveUnitPrice($product, $dealer);
        if ($unitPrice === null) {
            $unitPrice = isset($line['retailPrice'])
                ? (float)$line['retailPrice']
                : $this->parsePriceDisplay($line['priceDisplay'] ?? null);
        }
        $lineTotal = round($unitPrice * $quantity, 2);

        $line['unitPrice'] = $unitPrice;
        $line['lineTotal'] = $lineTotal;
        $line['retailLineTotal'] = $retailLineTotal ?? $lineTotal;
        $line['dealerDiscountAmount'] = $retailLineTotal !== null
            ? round(max(0, $retailLineTotal - $lineTotal), 2)
            : 0.0;
        $line['comment'] = $item->comment;
        $line['attachment'] = $item->buildAttachmentPayload();
        if ($line['attachment'] !== null) {
            $line['attachment']['downloadUrl'] = $this->attachmentUploadService->buildDownloadUrl((string)$product->slug);
        }

        $line['catalogProductId'] = (int)$product->id;

        $line = $this->applyCatalogPromotionToLine($line, $product, $quantity, $dealer);
        $line['cashbackEligible'] = $dealer !== null
            && $dealer->isDealer()
            && empty($line['hasCatalogPromotion']);

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCartLineForProduct(CatalogProduct $product, int $quantity, ?User $dealer = null): array
    {
        $item = new CartItem([
            'catalog_product_id' => (int)$product->id,
            'quantity' => $quantity,
        ]);
        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item, $dealer);
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function applyCatalogPromotionToLine(array $line, CatalogProduct $product, int $quantity, ?User $dealer): array
    {
        $line['hasCatalogPromotion'] = false;
        $line['catalogPromotionDiscount'] = 0.0;
        $line['catalogPromotion'] = null;

        if ($dealer === null || !$dealer->isDealer()) {
            return $line;
        }

        $pricingService = new DealerPricingService();
        $retailUnit = $pricingService->resolveRetailPrice($product);
        $modelId = $product->model_id !== null ? (int)$product->model_id : 0;
        $resolver = new CatalogPromotionResolver();
        $promotion = $resolver->findForProduct((int)$product->id, $modelId);
        if ($promotion === null) {
            return $line;
        }

        $promoUnit = CatalogPromotionPricing::resolveUnitPrice($retailUnit, $promotion);
        if ($promoUnit === null) {
            return $line;
        }

        $promotionDiscount = $resolver->calculateDiscountAmount($promotion, $retailUnit, $quantity);
        if ($promotionDiscount <= 0) {
            return $line;
        }

        $payableLineTotal = round($promoUnit * $quantity, 2);
        $retailLineTotal = isset($line['retailLineTotal']) ? (float)$line['retailLineTotal'] : null;

        $line['hasCatalogPromotion'] = true;
        $line['unitPrice'] = (float)$promoUnit;
        $line['catalogPromotionDiscount'] = $promotionDiscount;
        $line['catalogPromotion'] = CatalogPromotionPricing::toApiPayload($promotion);
        $line['lineTotal'] = $payableLineTotal;
        $line['dealerDiscountAmount'] = $retailLineTotal !== null
            ? round(max(0, $retailLineTotal - $payableLineTotal), 2)
            : (float)($line['dealerDiscountAmount'] ?? 0);

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateItem(ApiOwnerContext $owner, string $productSlug, int $quantity): array
    {
        $product = $this->findActiveProduct($productSlug);
        if ($quantity < 1) {
            throw new ApiValidationException('Ошибка валидации.', [
                'quantity' => ['Количество должно быть не меньше 1.'],
            ]);
        }

        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item === null) {
            throw new NotFoundHttpException('Товар не найден в корзине.');
        }

        $item->quantity = $quantity;
        if (!$item->save()) {
            throw new ApiValidationException('Не удалось обновить корзину.', $item->getErrors());
        }

        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateItemComment(ApiOwnerContext $owner, User $user, string $productSlug, ?string $comment): array
    {
        if (!$user->isDealer()) {
            throw new ForbiddenHttpException('Комментарии к позициям доступны только дилерам.');
        }

        $product = $this->findActiveProduct($productSlug);
        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item === null) {
            throw new NotFoundHttpException('Товар не найден в корзине.');
        }

        $comment = trim((string)$comment);
        $item->comment = $comment !== '' ? $comment : null;
        if (!$item->save()) {
            throw new ApiValidationException('Не удалось сохранить комментарий.', $item->getErrors());
        }

        $item->populateRelation('product', $product);

        return $this->buildLineFromItem($item);
    }

    public function removeItem(ApiOwnerContext $owner, string $productSlug): void
    {
        $product = CatalogProduct::find()->where(['slug' => $productSlug])->one();
        if ($product === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        $item = CartItem::findOne($this->ownerCondition($owner, (int)$product->id));
        if ($item !== null) {
            $this->deleteCartItemAttachmentFile($item);
        }

        CartItem::deleteAll($this->ownerCondition($owner, (int)$product->id));
    }

    /**
     * @return list<CartItem>
     */
    public function getItemsForOwner(ApiOwnerContext $owner): array
    {
        return $this->findItems($owner);
    }

    public function clear(ApiOwnerContext $owner): void
    {
        $items = CartItem::find()->where($this->ownerWhere($owner))->all();
        foreach ($items as $item) {
            $this->deleteCartItemAttachmentFile($item);
        }

        CartItem::deleteAll($this->ownerWhere($owner));
    }

    /**
     * @return array{mergedCount: int, resultTotal: int}
     */
    public function sync(User $user, string $sessionId): array
    {
        $sessionId = (new GuestSessionService())->normalize($sessionId);
        if ($sessionId === '') {
            throw new BadRequestHttpException('Поле sessionId обязательно.');
        }

        $userId = (int)$user->id;
        $guest = GuestSession::findOne(['session_id' => $sessionId]);
        $hasGuestItems = CartItem::find()->where(['session_id' => $sessionId])->exists();
        if ($guest !== null && $guest->cart_merged_at !== null && !$hasGuestItems) {
            return [
                'mergedCount' => 0,
                'resultTotal' => $this->countItemsForUser($userId),
            ];
        }

        $guestItems = CartItem::find()->where(['session_id' => $sessionId])->all();
        $merged = 0;

        foreach ($guestItems as $guestItem) {
            $userItem = CartItem::find()
                ->where(['user_id' => $userId, 'catalog_product_id' => $guestItem->catalog_product_id])
                ->one();

            if ($userItem !== null) {
                $userItem->quantity = (int)$userItem->quantity + (int)$guestItem->quantity;
                if (($userItem->comment === null || trim((string)$userItem->comment) === '')
                    && $guestItem->comment !== null
                    && trim((string)$guestItem->comment) !== '') {
                    $userItem->comment = $guestItem->comment;
                }
                if (($userItem->attachment_path === null || trim((string)$userItem->attachment_path) === '')
                    && $guestItem->attachment_path !== null
                    && trim((string)$guestItem->attachment_path) !== '') {
                    $userItem->attachment_path = $guestItem->attachment_path;
                    $userItem->attachment_original_name = $guestItem->attachment_original_name;
                    $guestItem->attachment_path = null;
                    $guestItem->attachment_original_name = null;
                }
                $userItem->save(false);
                $guestItem->delete();
            } else {
                $guestItem->user_id = $userId;
                $guestItem->session_id = null;
                $guestItem->save(false);
            }
            $merged++;
        }

        if ($guest !== null) {
            $guest->cart_merged_at = date('Y-m-d H:i:s');
            $guest->save(false, ['cart_merged_at', 'updated_at']);
        }

        return [
            'mergedCount' => $merged,
            'resultTotal' => $this->countItemsForUser($userId),
        ];
    }

    /**
     * @return list<CartItem>
     */
    private function findItems(ApiOwnerContext $owner): array
    {
        return CartItem::find()
            ->where($this->ownerWhere($owner))
            ->with(['product.image'])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    private function countItemsForUser(int $userId): int
    {
        return (int)CartItem::find()->where(['user_id' => $userId])->sum('quantity');
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

    private function assignOwner(CartItem $item, ApiOwnerContext $owner): void
    {
        if ($owner->userId !== null) {
            $item->user_id = $owner->userId;
            $item->session_id = null;
            return;
        }

        $item->user_id = null;
        $item->session_id = $owner->sessionId;
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

    private function parsePriceDisplay(?string $display): float
    {
        if ($display === null || trim($display) === '') {
            return 0.0;
        }

        $digits = preg_replace('/[^\d]/', '', $display);

        return $digits !== '' ? (float)$digits : 0.0;
    }

    private function deleteCartItemAttachmentFile(CartItem $item): void
    {
        if (!$item->hasAttribute('attachment_path')) {
            return;
        }

        $this->attachmentUploadService->deleteStoredFile($item->attachment_path);
    }

    private function resolveDealerViewer(?User $viewer): ?User
    {
        if ($viewer !== null && $viewer->isDealer()) {
            return $viewer;
        }

        return null;
    }

    private function resolveDealerForItem(CartItem $item): ?User
    {
        if ($item->user_id === null) {
            return null;
        }

        $user = User::findOne((int)$item->user_id);

        return ($user !== null && $user->isDealer()) ? $user : null;
    }
}
