<?php

namespace app\controllers\api\v1;

use app\services\cart\CartCheckoutService;
use app\services\cart\CartService;
use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerActivityLogger;
use app\services\guest\ApiOwnerContext;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class CartController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly CartService $cartService = new CartService(),
        private readonly CartCheckoutService $checkoutService = new CartCheckoutService(),
        private readonly \app\services\cart\CartAttachmentUploadService $attachmentUploadService = new \app\services\cart\CartAttachmentUploadService(),
        private readonly DealerAccessGuard $dealerAccessGuard = new DealerAccessGuard(),
        private readonly DealerActivityLogger $activityLogger = new DealerActivityLogger(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['options'];
        $behaviors['authenticator']['optional'] = [
            'index',
            'add-item',
            'update-item',
            'update-item-comment',
            'remove-item',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
            'add-item' => ['POST', 'OPTIONS'],
            'update-item' => ['PATCH', 'OPTIONS'],
            'update-item-comment' => ['PATCH', 'OPTIONS'],
            'upload-item-attachment' => ['POST', 'OPTIONS'],
            'remove-item-attachment' => ['DELETE', 'OPTIONS'],
            'download-item-attachment' => ['GET', 'OPTIONS'],
            'remove-item' => ['DELETE', 'OPTIONS'],
            'sync' => ['POST', 'OPTIONS'],
            'apply-promo' => ['POST', 'OPTIONS'],
            'remove-promo' => ['DELETE', 'OPTIONS'],
            'apply-cashback' => ['PATCH', 'OPTIONS'],
            'remove-cashback' => ['DELETE', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cart",
     *     tags={"Корзина"},
     *     summary="Текущая корзина",
     *     description="Гость: X-Session-ID или sessionId. Авторизованный: Bearer. Позиции: retailPrice, dealerPrice (дилер), retailLineTotal, dealerDiscountAmount. Итоги: retailSubtotal, dealerDiscountAmount, subtotal, discounts (dealer→promo→cashback). Дилер: comment и attachment к позициям.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Состав корзины",
     *         @OA\JsonContent(ref="#/components/schemas/CartResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionIndex(): array
    {
        $owner = ApiOwnerContext::resolve();
        $user = $owner->identity();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);

        return $this->cartService->getCartPayload($owner, $user);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/items",
     *     tags={"Корзина"},
     *     summary="Добавить товар в корзину",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CartAddItemRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Добавленная позиция",
     *         @OA\JsonContent(ref="#/components/schemas/CartLineItem")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionAddItem(): array
    {
        $owner = ApiOwnerContext::resolve();
        $user = $owner->identity();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);

        $payload = Yii::$app->request->getBodyParams();
        $productId = trim((string)($payload['productId'] ?? ''));
        $quantity = (int)($payload['quantity'] ?? 1);

        $result = $this->cartService->addItem($owner, $productId, $quantity);
        if ($user !== null && $user->isDealer()) {
            $this->activityLogger->log($user, 'cart.add', ['productId' => $productId, 'quantity' => $quantity]);
        }

        return $result;
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/cart/items/{productId}",
     *     tags={"Корзина"},
     *     summary="Изменить количество товара в корзине",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CartUpdateItemRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Обновлённая позиция",
     *         @OA\JsonContent(ref="#/components/schemas/CartLineItem")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Позиция не найдена")
     * )
     */
    public function actionUpdateItem(string $productId): array
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        $payload = Yii::$app->request->getBodyParams();
        $quantity = (int)($payload['quantity'] ?? 0);

        return $this->cartService->updateItem($owner, $productId, $quantity);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/cart/items/{productId}/comment",
     *     tags={"Корзина"},
     *     summary="Комментарий к позиции (только дилер)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CartItemCommentRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Позиция с комментарием",
     *         @OA\JsonContent(ref="#/components/schemas/CartLineItem")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Только дилер")
     * )
     */
    public function actionUpdateItemComment(string $productId): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        $owner = ApiOwnerContext::resolve();

        $payload = Yii::$app->request->getBodyParams();
        $comment = $payload['comment'] ?? null;

        return $this->cartService->updateItemComment($owner, $user, $productId, is_string($comment) ? $comment : null);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/items/{productId}/attachment",
     *     tags={"Корзина"},
     *     summary="Загрузить файл к позиции (только дилер)",
     *     description="Один файл на позицию. Любые форматы, max 50 МБ. При оформлении заказа файл переносится в позицию заказа автоматически.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"attachment"},
     *                 @OA\Property(property="attachment", type="string", format="binary", description="Файл к позиции")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Позиция с файлом",
     *         @OA\JsonContent(ref="#/components/schemas/CartLineItem")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Только дилер")
     * )
     */
    public function actionUploadItemAttachment(string $productId): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        $owner = ApiOwnerContext::resolve();

        $file = UploadedFile::getInstanceByName('attachment');
        if ($file === null) {
            throw new \app\exceptions\ApiValidationException('Файл не передан.', [
                'attachment' => ['Выберите файл для загрузки.'],
            ]);
        }

        return $this->cartService->uploadItemAttachment($owner, $user, $productId, $file);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/items/{productId}/attachment",
     *     tags={"Корзина"},
     *     summary="Удалить файл позиции (только дилер)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Позиция без файла",
     *         @OA\JsonContent(ref="#/components/schemas/CartLineItem")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Только дилер"),
     *     @OA\Response(response=404, description="Позиция или файл не найдены")
     * )
     */
    public function actionRemoveItemAttachment(string $productId): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        $owner = ApiOwnerContext::resolve();

        return $this->cartService->removeItemAttachment($owner, $user, $productId);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cart/items/{productId}/attachment",
     *     tags={"Корзина"},
     *     summary="Скачать файл позиции корзины (только дилер)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\Response(response=200, description="Файл вложения"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Только дилер"),
     *     @OA\Response(response=404, description="Позиция или файл не найдены")
     * )
     */
    public function actionDownloadItemAttachment(string $productId): Response
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        $owner = ApiOwnerContext::resolve();

        $item = $this->cartService->findItemAttachment($owner, $user, $productId);
        $path = $this->attachmentUploadService->resolveAbsolutePath((string)$item->attachment_path);
        if (!is_file($path)) {
            throw new \yii\web\NotFoundHttpException('Файл не найден.');
        }

        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $path,
            $item->attachment_original_name ?: basename((string)$item->attachment_path),
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/items/{productId}",
     *     tags={"Корзина"},
     *     summary="Удалить товар из корзины",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", description="slug товара (SKU)")
     *     ),
     *     @OA\Response(response=204, description="Удалено"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionRemoveItem(string $productId): void
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        $this->cartService->removeItem($owner, $productId);
        Yii::$app->response->statusCode = 204;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/sync",
     *     tags={"Корзина"},
     *     summary="Объединить гостевую корзину с пользовательской",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/CartSyncRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Результат объединения",
     *         @OA\JsonContent(ref="#/components/schemas/CartSyncResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSync(): array
    {
        $user = $this->requireUser();
        $body = Yii::$app->request->bodyParams;
        $sessionId = trim((string)($body['sessionId'] ?? $body['session_id'] ?? Yii::$app->request->headers->get('X-Session-ID', '')));

        return $this->cartService->sync($user, $sessionId);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/promo",
     *     tags={"Корзина"},
     *     summary="Применить промокод (только дилер)",
     *     description="Промокод и кэшбек взаимоисключающие: при применении промо списание кэшбека сбрасывается.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CartApplyPromoRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Корзина с применённым промокодом",
     *         @OA\JsonContent(ref="#/components/schemas/CartResponse")
     *     ),
     *     @OA\Response(response=400, description="Промокод недоступен", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован или не дилер"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionApplyPromo(): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        if (!$user->isDealer()) {
            throw new UnauthorizedHttpException('Доступ только для дилеров.');
        }

        $code = trim((string)(Yii::$app->request->getBodyParams()['code'] ?? ''));
        $this->checkoutService->applyPromo($user, $code);
        $owner = ApiOwnerContext::resolve(true);

        return $this->cartService->getCartPayload($owner, $user);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/promo",
     *     tags={"Корзина"},
     *     summary="Убрать промокод из корзины",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Корзина без промокода",
     *         @OA\JsonContent(ref="#/components/schemas/CartResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionRemovePromo(): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        if (!$user->isDealer()) {
            throw new UnauthorizedHttpException('Доступ только для дилеров.');
        }
        $this->checkoutService->clearPromo((int)$user->getId());
        $owner = ApiOwnerContext::resolve(true);

        return $this->cartService->getCartPayload($owner, $user);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/cart/cashback",
     *     tags={"Корзина"},
     *     summary="Применить кэшбек (только дилер)",
     *     description="Кэшбек нельзя использовать вместе с промокодом.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(ref="#/components/schemas/CartApplyCashbackRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Корзина с применённым кэшбеком",
     *         @OA\JsonContent(ref="#/components/schemas/CartResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка (например, промо уже применён)", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован или не дилер"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionApplyCashback(): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        if (!$user->isDealer()) {
            throw new UnauthorizedHttpException('Доступ только для дилеров.');
        }

        $payload = Yii::$app->request->getBodyParams();
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;
        $this->checkoutService->applyCashback($user, $amount);
        $owner = ApiOwnerContext::resolve(true);

        return $this->cartService->getCartPayload($owner, $user);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/cashback",
     *     tags={"Корзина"},
     *     summary="Убрать кэшбек из корзины",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Корзина без кэшбека",
     *         @OA\JsonContent(ref="#/components/schemas/CartResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionRemoveCashback(): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);
        if (!$user->isDealer()) {
            throw new UnauthorizedHttpException('Доступ только для дилеров.');
        }
        $this->checkoutService->clearCashback((int)$user->getId());
        $owner = ApiOwnerContext::resolve(true);

        return $this->cartService->getCartPayload($owner, $user);
    }

    private function requireUser(): \app\models\User
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null || !($identity instanceof \app\models\User)) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        return $identity;
    }
}
