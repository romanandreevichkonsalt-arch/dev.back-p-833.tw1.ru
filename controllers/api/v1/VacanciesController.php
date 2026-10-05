<?php

namespace app\controllers\api\v1;

use app\services\vacancy\VacancyService;
use Yii;

class VacanciesController extends ApiController
{
    private VacancyService $vacancies;

    public function init(): void
    {
        parent::init();
        $this->vacancies = Yii::$container->get(VacancyService::class);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'][] = 'view';

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'view' => ['GET', 'OPTIONS'],
        ];
    }

    /** Legacy route GET /api/v1/vacancies/{slug} — см. GET /api/v1/pages/vacancies/{slug} в Swagger. */
    public function actionView(string $slug): array
    {
        return $this->vacancies->getBySlug($slug);
    }
}
