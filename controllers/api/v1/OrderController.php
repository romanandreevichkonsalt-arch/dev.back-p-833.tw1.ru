<?php

namespace app\controllers\api\v1;

use app\exceptions\ApiValidationException;
use app\models\User;
use app\services\cart\CartService;
use app\services\dealer\DealerAccessGuard;
use app\services\guest\ApiOwnerContext;
use app\services\order\OrderApiService;
use app\services\order\OrderAttachmentUploadService;
use app\services\order\OrderDocumentUploadService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class OrderController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly OrderApiService $orderService = new OrderApiService(),
        private readonly CartService $cartService = new CartService(),
        private readonly DealerAccessGuard $dealerAccessGuard = new DealerAccessGuard(),
        private readonly OrderAttachmentUploadService $attachmentUploadService = new OrderAttachmentUploadService(),
        private readonly OrderDocumentUploadService $documentUploadService = new OrderDocumentUploadService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['payment-status', 'options'];
        $behaviors['authenticator']['optional'] = ['create', 'view', 'download-item-attachment', 'download-document'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
            'view' => ['GET', 'OPTIONS'],
            'payment-status' => ['GET', 'OPTIONS'],
            'create' => ['POST', 'OPTIONS'],
            'download-item-attachment' => ['GET', 'OPTIONS'],
            'download-document' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders",
     *     tags={"Заказы"},
     *     summary="Список заказов текущего пользователя",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Список заказов",
     *         @OA\JsonContent(ref="#/components/schemas/OrderListResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionIndex(): array
    {
        $user = $this->requireUser();
        $this->dealerAccessGuard->ensureDealerProfileComplete($user);

        return [
            'items' => $this->orderService->listOrders((int)$user->getId()),
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{number}",
     *     tags={"Заказы"},
     *     summary="Детали заказа",
     *     description="Авторизованный: свой заказ. Гость: заказ по X-Session-ID. Онлайн-оплата: при GET для pending_payment статус синхронизируется с API ЮKassa (return_url раньше webhook). Позиции: snapshot retailPrice, dealerPrice, retailLineTotal, dealerDiscountAmount, paidLineTotal. Итоги: retailSubtotalAmount, dealerDiscountAmount, discounts; totalAmount включает cashlessSurchargeAmount.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(
     *         name="number",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", example="ORD-20260819-A1B2C")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Заказ",
     *         @OA\JsonContent(ref="#/components/schemas/OrderResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError")),
     *     @OA\Response(response=404, description="Заказ не найден")
     * )
     */
    public function actionView(string $number): array
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        return $this->orderService->getOrder($owner, $number);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{number}/payment-status",
     *     tags={"Заказы"},
     *     summary="Статус оплаты гостевого заказа (публичный)",
     *     description="После return_url ЮKassa: опрос по number из URL, без Bearer и X-Session-ID. Синхронизация с API ЮKassa. paymentReturnTarget=success — оставаться на странице и опрашивать; при cancelled — paymentCartReturnUrl.",
     *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Статус", @OA\JsonContent(ref="#/components/schemas/OrderPaymentStatusResponse")),
     *     @OA\Response(response=404, description="Не гостевой онлайн-заказ")
     * )
     *
     * @return array<string, mixed>
     */
    public function actionPaymentStatus(string $number): array
    {
        return $this->orderService->getGuestPaymentStatusPublic($number);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{number}/items/{productId}/attachment",
     *     tags={"Заказы"},
     *     summary="Скачать файл позиции заказа",
     *     description="Доступен владельцу заказа: дилер по Bearer или гость по X-Session-ID.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="productId", in="path", required=true, description="slug позиции (productId)", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Файл вложения"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен"),
     *     @OA\Response(response=404, description="Заказ, позиция или файл не найдены")
     * )
     */
    public function actionDownloadItemAttachment(string $number, string $productId): Response
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        $item = $this->orderService->findOrderItemAttachment($owner, $number, $productId);
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
     * @OA\Get(
     *     path="/api/v1/orders/{number}/documents/{documentId}",
     *     tags={"Заказы"},
     *     summary="Скачать документ заказа",
     *     description="Счёт, ОПД или другой документ, загруженный менеджером. Доступен владельцу заказа.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="number", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Файл документа"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=403, description="Профиль дилера не заполнен"),
     *     @OA\Response(response=404, description="Заказ или документ не найдены")
     * )
     */
    public function actionDownloadDocument(string $number, int $documentId): Response
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        $document = $this->orderService->findOrderDocument($owner, $number, $documentId);
        $path = $this->documentUploadService->resolveAbsolutePath((string)$document->stored_path);
        if (!is_file($path)) {
            throw new \yii\web\NotFoundHttpException('Файл не найден.');
        }

        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $path,
            $document->original_name ?: basename((string)$document->stored_path),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"Заказы"},
     *     summary="Создать заказ",
     *     description="Два режима (Bearer или X-Session-ID). 1) Одним запросом: items[] с productId и quantity; unitPrice из каталога; дилер — comment и itemAttachments[productId] (multipart). 2) Из корзины: items без quantity. Гость (X-Session-ID): pending_payment + paymentConfirmationUrl; корзина сохраняется до успешной оплаты (webhook); return_url ведёт на корзину (paymentReturnUrl). Дилер: заявка менеджеру, без ЮKassa. Обязательны customerName, customerPhone.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/OrderCreateRequest")
     *         ),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/OrderCreateMultipartRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Созданный заказ",
     *         @OA\JsonContent(ref="#/components/schemas/OrderResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=403, description="Гостю недоступны comment/attachment или профиль дилера не заполнен", @OA\JsonContent(ref="#/components/schemas/ProfileIncompleteError"))
     * )
     */
    public function actionCreate(): array
    {
        $owner = ApiOwnerContext::resolve();
        $this->dealerAccessGuard->ensureDealerProfileComplete($owner->identity());

        $request = Yii::$app->request;
        $payload = str_starts_with((string)$request->contentType, 'multipart/form-data')
            ? $request->post()
            : $request->getBodyParams();

        if (UploadedFile::getInstanceByName('attachment') !== null) {
            throw new ApiValidationException('Файл на уровне заказа не поддерживается.', [
                'attachment' => ['Прикрепите файл к позиции: itemAttachments[productId].'],
            ]);
        }

        $itemAttachments = $this->collectItemAttachments($owner, is_array($payload) ? $payload : []);

        $order = $this->orderService->createFromCart(
            $owner,
            is_array($payload) ? $payload : [],
            $itemAttachments,
        );
        Yii::$app->response->statusCode = 201;

        return $order;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, UploadedFile>
     */
    private function collectItemAttachments(ApiOwnerContext $owner, array $payload): array
    {
        $slugs = [];
        $items = $payload['items'] ?? null;
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : null;
        }
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $slug = trim((string)($item['productId'] ?? ''));
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        foreach ($this->cartService->getItemsForOwner($owner) as $cartItem) {
            $slug = $cartItem->product?->slug;
            if ($slug !== null && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        $attachments = [];
        foreach (array_values(array_unique($slugs)) as $slug) {
            $file = UploadedFile::getInstanceByName('itemAttachments[' . $slug . ']');
            if ($file !== null) {
                $attachments[$slug] = $file;
            }
        }

        return $attachments;
    }

    private function requireUser(): User
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null || !($identity instanceof User)) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        return $identity;
    }
}
