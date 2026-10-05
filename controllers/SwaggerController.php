<?php

namespace app\controllers;

use app\services\docs\OpenApiGeneratorService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\Controller;
use yii\web\Response;

class SwaggerController extends Controller
{
    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/swagger/json-schema",
     *     tags={"Документация"},
     *     summary="Возвращает сгенерированную OpenAPI-схему в формате JSON",
     *     @OA\Response(
     *         response=200,
     *         description="OpenAPI-схема",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function actionJsonSchema(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');

        $service = new OpenApiGeneratorService();

        $fileJson = $service->readFromRuntime();
        if ($fileJson !== null && $service->isRuntimeFresh()) {
            return $fileJson;
        }

        $cache = Yii::$app->cache;
        $sourceVersion = $service->getSourceVersion();
        $cached = $cache->get(OpenApiGeneratorService::CACHE_KEY);
        if (
            is_array($cached)
            && ($cached['version'] ?? '') === $sourceVersion
            && isset($cached['json'])
            && $service->isValidJson($cached['json'])
        ) {
            return $cached['json'];
        }

        if ($fileJson !== null) {
            return $fileJson;
        }

        try {
            $json = $service->generateJson();
        } catch (\Throwable $e) {
            Yii::error('OpenAPI generation failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 503;
            return json_encode([
                'message' => 'OpenAPI schema is not ready. Run: php yii open-api/generate',
            ], JSON_UNESCAPED_UNICODE);
        }

        $cache->set(OpenApiGeneratorService::CACHE_KEY, [
            'version' => $sourceVersion,
            'json' => $json,
        ], 86400);

        return $json;
    }
}
