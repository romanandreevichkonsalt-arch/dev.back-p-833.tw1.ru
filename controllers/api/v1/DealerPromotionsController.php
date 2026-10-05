<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\promotion\PromotionDealerApiService;
use OpenApi\Annotations as OA;

class DealerPromotionsController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly PromotionDealerApiService $promotionService = new PromotionDealerApiService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function verbs(): array
    {
        return [
            'banners' => ['GET', 'OPTIONS'],
            'popup' => ['GET', 'OPTIONS'],
            'sales' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dealer/promotions/banners",
     *     tags={"ЛКД — промо"},
     *     summary="Все активные баннеры акций в ЛКД",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="items — все баннеры «В эфире»; несколько записей из админки возвращаются целиком",
     *         @OA\JsonContent(ref="#/components/schemas/DealerPromotionBannersResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionBanners(): array
    {
        $this->accessGuard->requireDealer();

        return [
            'items' => $this->promotionService->listVisibleBanners(),
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dealer/promotions/popup",
     *     tags={"ЛКД — промо"},
     *     summary="Всплывающие баннеры акций в ЛКД",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="items — все активные попапы; popup — первый из items (совместимость)",
     *         @OA\JsonContent(ref="#/components/schemas/DealerPromotionPopupResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPopup(): array
    {
        $this->accessGuard->requireDealer();

        $items = $this->promotionService->listVisiblePopups();

        return [
            'items' => $items,
            'popup' => $items[0] ?? null,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dealer/promotions/sales",
     *     tags={"ЛКД — промо"},
     *     summary="Активные акции каталога",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Скидки на модели/SKU в текущем периоде",
     *         @OA\JsonContent(ref="#/components/schemas/DealerCatalogPromotionsResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSales(): array
    {
        $this->accessGuard->requireDealer();

        return [
            'items' => $this->promotionService->listActiveCatalogPromotions(),
        ];
    }
}
