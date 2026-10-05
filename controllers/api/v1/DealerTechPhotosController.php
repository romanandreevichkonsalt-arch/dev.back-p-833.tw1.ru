<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerModelTechPhotosService;
use OpenApi\Annotations as OA;

class DealerTechPhotosController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly DealerModelTechPhotosService $techPhotosService = new DealerModelTechPhotosService(),
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
     *     path="/api/v1/dealer/tech-photos",
     *     tags={"ЛКД — профиль"},
     *     summary="Ссылки на диск с тех. фото по коллекциям",
     *     description="Для каждой коллекции мебели — одна строка: наименование коллекции и ссылка на облачную папку (из «Ссылка на диск с тех.фото» у модели). Если у всех коллекций одна и та же папка, дублируется в поле url. Файлы из медиатеки (dimensionImages) не перечисляются.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="url — общая ссылка, если она одна на все коллекции; items — label + url",
     *         @OA\JsonContent(ref="#/components/schemas/DealerModelTechPhotosResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль не заполнен")
     * )
     */
    public function actionIndex(): array
    {
        $user = $this->accessGuard->requireDealer();
        $this->accessGuard->requireCompleteProfile($user);

        return $this->techPhotosService->listFolderLinks();
    }
}
