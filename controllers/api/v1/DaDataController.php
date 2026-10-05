<?php

declare(strict_types=1);

namespace app\controllers\api\v1;

use app\components\dadata\DaDataClient;
use app\components\dadata\DaDataSuggestionFormatter;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;

class DaDataController extends ApiController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['suggest-city', 'suggest-address', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'suggest-city' => ['POST', 'OPTIONS'],
            'suggest-address' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dadata/suggest/city",
     *     tags={"DaData"},
     *     summary="Подсказки города (DaData)",
     *     description="Поиск населённого пункта по названию. Используйте data.city_fias_id и postal_code для доставки и checkout. Авторизация не требуется. Без DADATA_API_KEY возвращаются тестовые подсказки.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DaDataSuggestRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список подсказок",
     *         @OA\JsonContent(ref="#/components/schemas/DaDataSuggestResponse")
     *     ),
     *     @OA\Response(response=400, description="query обязателен")
     * )
     */
    public function actionSuggestCity(): array
    {
        [$query, $count] = $this->resolveSuggestParams();

        $client = new DaDataClient();
        $suggestions = $client->suggestCity($query, $count);

        return ['data' => DaDataSuggestionFormatter::formatMany($suggestions)];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dadata/suggest/address",
     *     tags={"DaData"},
     *     summary="Подсказки полного адреса (DaData)",
     *     description="Поиск по одной строке (город, улица, дом). Данные из DaData (ключ dadataApiKey). Авторизация не требуется. Без DADATA_API_KEY возвращаются тестовые подсказки.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DaDataSuggestRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список подсказок",
     *         @OA\JsonContent(ref="#/components/schemas/DaDataSuggestResponse")
     *     ),
     *     @OA\Response(response=400, description="query обязателен")
     * )
     */
    public function actionSuggestAddress(): array
    {
        [$query, $count] = $this->resolveSuggestParams();

        $client = new DaDataClient();
        $suggestions = $client->suggestFullAddress($query, $count);

        return ['data' => DaDataSuggestionFormatter::formatMany($suggestions)];
    }

    /** @return array{0: string, 1: int} */
    private function resolveSuggestParams(): array
    {
        $body = Yii::$app->request->bodyParams;
        $query = trim((string) ($body['query'] ?? ''));
        $count = min(20, max(1, (int) ($body['count'] ?? 10)));

        if ($query === '') {
            throw new BadRequestHttpException('query is required.');
        }

        return [$query, $count];
    }
}
