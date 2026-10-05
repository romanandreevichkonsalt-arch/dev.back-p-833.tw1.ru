<?php

namespace app\controllers\api\v1;

use app\services\dealer\CashbackService;
use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerPromoService;
use OpenApi\Annotations as OA;

class DealerBonusesController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly CashbackService $cashbackService = new CashbackService(),
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
     *     path="/api/v1/dealer/bonuses",
     *     tags={"ЛКД — бонусы"},
     *     summary="Промокоды и кэшбек дилера",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Мои бонусы",
     *         @OA\JsonContent(ref="#/components/schemas/DealerBonusesResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionIndex(): array
    {
        $user = $this->accessGuard->requireDealer();

        return [
            'promos' => $this->promoService->listActiveBonuses((int)$user->id),
            'cashback' => $this->cashbackService->getWidget((int)$user->id),
        ];
    }
}
