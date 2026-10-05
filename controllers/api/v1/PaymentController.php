<?php

namespace app\controllers\api\v1;

use app\models\Order;
use app\services\payment\YooKassaDevSupport;
use app\services\payment\YooKassaHttpLogger;
use app\services\payment\YooKassaPaymentService;
use app\services\payment\YooKassaWebhookIpAllowlist;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class PaymentController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly YooKassaPaymentService $yooKassaPaymentService = new YooKassaPaymentService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'yookassa-webhook',
            'yookassa-test-scenarios',
            'yookassa-test-succeed',
            'yookassa-test-cancel',
            'yookassa-test-insufficient-funds',
            'options',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'yookassa-webhook' => ['POST', 'OPTIONS'],
            'yookassa-test-scenarios' => ['GET', 'OPTIONS'],
            'yookassa-test-succeed' => ['POST', 'OPTIONS'],
            'yookassa-test-cancel' => ['POST', 'OPTIONS'],
            'yookassa-test-insufficient-funds' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/yookassa/webhook",
     *     tags={"Оплата"},
     *     summary="Webhook ЮKassa (HTTP-уведомления)",
     *     description="Публичный endpoint для payment.succeeded / payment.canceled. URL: ЛК ЮKassa → Интеграция → HTTP-уведомления. Проверка IP allowlist + GET payment API. Ответ HTTP 200 подтверждает получение.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/YooKassaWebhookNotification")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Уведомление принято",
     *         @OA\JsonContent(
     *             required={"ok"},
     *             @OA\Property(property="ok", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Некорректное тело"),
     *     @OA\Response(response=403, description="IP не из списка ЮKassa"),
     *     @OA\Response(response=500, description="Временная ошибка проверки платежа — ЮKassa повторит доставку")
     * )
     *
     * @return array{ok: bool}
     */
    public function actionYookassaWebhook(): array
    {
        $this->assertWebhookIpAllowed();

        $payload = Yii::$app->request->getBodyParams();
        if (!is_array($payload) || $payload === []) {
            $raw = Yii::$app->request->getRawBody();
            $decoded = json_decode($raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if ($payload === []) {
            YooKassaHttpLogger::logWebhook('reject', ['reason' => 'empty body']);
            Yii::$app->response->statusCode = 400;

            return ['ok' => false];
        }

        YooKassaHttpLogger::logWebhook('in', [
            'event' => $payload['event'] ?? null,
            'paymentId' => is_array($payload['object'] ?? null) ? ($payload['object']['id'] ?? null) : null,
            'body' => $payload,
        ]);

        try {
            $this->yooKassaPaymentService->handleNotification($payload);
            YooKassaHttpLogger::logWebhook('out', ['ok' => true]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($e instanceof \RuntimeException
                && str_contains($message, "Payment doesn't exist or access denied")) {
                YooKassaHttpLogger::logWebhook('ignored', ['reason' => $message]);

                return ['ok' => true];
            }

            YooKassaHttpLogger::logWebhook('error', ['message' => $message]);
            Yii::error('YooKassa webhook failed: ' . $message, __METHOD__);
            if ($e instanceof \RuntimeException && str_contains($message, 'YooKassa')) {
                Yii::$app->response->statusCode = 500;

                return ['ok' => false];
            }
        }

        return ['ok' => true];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/yookassa/test/scenarios",
     *     tags={"Оплата"},
     *     summary="[Тест] Справка: карты и dev-endpoints",
     *     description="Доступно только при test_ secret или YOOKASSA_TEST_ENDPOINTS=1. Опционально заголовок X-Yookassa-Test-Token.",
     *     @OA\Response(
     *         response=200,
     *         description="Сценарии и тестовые карты ЮKassa",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=403, description="Неверный test token"),
     *     @OA\Response(response=404, description="Test endpoints отключены")
     * )
     *
     * @return array<string, mixed>
     */
    public function actionYookassaTestScenarios(): array
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        YooKassaDevSupport::assertTestRequestAllowed(is_array($params) ? $params : []);

        $params = is_array($params) ? $params : [];

        return array_merge(
            ['ok' => true, 'testEndpointsEnabled' => true],
            YooKassaDevSupport::scenariosPayload(),
            ['runtimeConfig' => YooKassaHttpLogger::configSummary($params)],
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/yookassa/test/succeed",
     *     tags={"Оплата"},
     *     summary="[Тест] Симуляция успешной оплаты",
     *     description="Применяет payment.succeeded к заказу в pending_payment (без формы ЮKassa).",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"orderNumber"},
     *             @OA\Property(property="orderNumber", type="string", example="ORD-20260919-A1B2C")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Заказ оплачен", @OA\JsonContent(ref="#/components/schemas/YooKassaTestSimulateResponse")),
     *     @OA\Response(response=400, description="Заказ не ждёт оплату"),
     *     @OA\Response(response=404, description="Заказ не найден или test endpoints отключены")
     * )
     *
     * @return array<string, mixed>
     */
    public function actionYookassaTestSucceed(): array
    {
        return $this->runTestSimulate('succeeded', 'payment.succeeded');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/yookassa/test/cancel",
     *     tags={"Оплата"},
     *     summary="[Тест] Симуляция отмены / отказа",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"orderNumber"},
     *             @OA\Property(property="orderNumber", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Заказ отменён", @OA\JsonContent(ref="#/components/schemas/YooKassaTestSimulateResponse")),
     *     @OA\Response(response=400, description="Заказ не ждёт оплату"),
     *     @OA\Response(response=404, description="Не найден или test endpoints отключены")
     * )
     *
     * @return array<string, mixed>
     */
    public function actionYookassaTestCancel(): array
    {
        return $this->runTestSimulate('canceled', 'payment.canceled');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/yookassa/test/insufficient-funds",
     *     tags={"Оплата"},
     *     summary="[Тест] Симуляция «недостаточно средств»",
     *     description="На форме ЮKassa — карта 5555555555554600; через API — тот же итог, что cancel.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"orderNumber"},
     *             @OA\Property(property="orderNumber", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, @OA\JsonContent(ref="#/components/schemas/YooKassaTestSimulateResponse")),
     *     @OA\Response(response=404, description="Test endpoints отключены")
     * )
     *
     * @return array<string, mixed>
     */
    public function actionYookassaTestInsufficientFunds(): array
    {
        $response = $this->runTestSimulate('canceled', 'payment.canceled');
        $response['scenario'] = 'insufficient_funds';

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function runTestSimulate(string $outcome, string $eventLabel): array
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        YooKassaDevSupport::assertTestRequestAllowed(is_array($params) ? $params : []);

        $orderNumber = trim((string)(Yii::$app->request->getBodyParam('orderNumber') ?? ''));
        if ($orderNumber === '') {
            throw new BadRequestHttpException('orderNumber is required.');
        }

        $order = Order::find()->where(['number' => $orderNumber])->one();
        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            throw new BadRequestHttpException('Order is not awaiting payment.');
        }

        $updated = $this->yooKassaPaymentService->applySimulatedOutcome($order, $outcome);

        return [
            'ok' => true,
            'simulatedEvent' => $eventLabel,
            'orderNumber' => (string)$updated->number,
            'status' => (string)$updated->status,
            'paymentStatus' => (string)$updated->payment_status,
            'paymentExternalId' => (string)$updated->payment_external_id,
        ];
    }

    private function assertWebhookIpAllowed(): void
    {
        $cidrs = Yii::$app->params['yookassa']['webhookIpCidrs'] ?? [];
        if (!is_array($cidrs) || $cidrs === []) {
            return;
        }

        $ip = (string)Yii::$app->request->userIP;
        if (!YooKassaWebhookIpAllowlist::isAllowed($ip, $cidrs)) {
            Yii::warning('YooKassa webhook rejected from IP: ' . $ip, __METHOD__);
            throw new ForbiddenHttpException('Forbidden');
        }
    }
}
