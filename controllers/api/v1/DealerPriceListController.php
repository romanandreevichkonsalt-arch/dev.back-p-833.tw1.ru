<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerPriceListService;
use OpenApi\Annotations as OA;
use yii\web\NotFoundHttpException;

class DealerPriceListController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly DealerPriceListService $priceListService = new DealerPriceListService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dealer/price-list",
     *     tags={"ЛКД — профиль"},
     *     summary="Прайс-листы дилера (common + personal)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Ссылка на прайс-лист",
     *         @OA\JsonContent(ref="#/components/schemas/DealerPriceListResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль не заполнен"),
     *     @OA\Response(response=404, description="Прайс-лист не настроен")
     * )
     */
    public function actionIndex(): array
    {
        $user = $this->accessGuard->requireDealer();
        $this->accessGuard->requireCompleteProfile($user);

        $payload = $this->priceListService->getPayloadWithLegacyFallback($user);
        if ($payload['common'] === null && $payload['personal'] === null) {
            throw new NotFoundHttpException('Прайс-лист недоступен.');
        }

        return $payload;
    }
}
