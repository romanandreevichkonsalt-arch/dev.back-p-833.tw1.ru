<?php

namespace app\controllers\api\v1;

use app\services\journal\JournalArticleService;
use OpenApi\Annotations as OA;
use Yii;

class JournalController extends ApiController
{
    private JournalArticleService $articles;

    public function init(): void
    {
        parent::init();
        $this->articles = Yii::$container->get(JournalArticleService::class);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'][] = 'article';

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'article' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/journal/articles/{slug}",
     *     tags={"Журнал"},
     *     summary="Полный контент статьи журнала",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="slug статьи",
     *         @OA\Schema(type="string", example="geometriya-komforta")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статья с блоками",
     *         @OA\JsonContent(ref="#/components/schemas/JournalArticleResponse")
     *     ),
     *     @OA\Response(response=404, description="Статья не найдена")
     * )
     */
    public function actionArticle(string $slug): array
    {
        return $this->articles->getBySlug($slug, $this->resolveOptionalUser());
    }
}
