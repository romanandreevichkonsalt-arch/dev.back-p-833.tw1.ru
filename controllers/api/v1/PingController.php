<?php

namespace app\controllers\api\v1;

use OpenApi\Annotations as OA;

class PingController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/ping",
     *     tags={"System"},
     *     summary="API health check",
     *     @OA\Response(
     *         response=200,
     *         description="Service is available",
     *         @OA\JsonContent(ref="#/components/schemas/PingResponse")
     *     )
     * )
     */
    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'service' => 'fabrika-backend-api',
            'version' => 'v1',
        ];
    }
}
