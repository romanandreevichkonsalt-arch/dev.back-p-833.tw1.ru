<?php

namespace app\controllers\api\v1;

use app\models\User;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;

abstract class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        // API is stateless; admin/web part keeps session auth enabled.
        \Yii::$app->user->enableSession = false;

        return parent::beforeAction($action);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        $origins = \Yii::$app->params['corsOrigins'] ?? ['*'];

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => $origins,
                'Access-Control-Request-Method' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Expose-Headers' => [
                    'X-Total-Count',
                    'X-Page',
                    'X-Per-Page',
                    'X-Sort',
                    'X-Scope-Mode',
                ],
                'Access-Control-Allow-Credentials' => false,
            ],
        ];

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['options'],
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
        ];
    }

    protected function resolveOptionalUser(): ?User
    {
        $header = Yii::$app->request->headers->get('Authorization');
        if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        $identity = User::findIdentityByAccessToken($matches[1]);

        return $identity instanceof User ? $identity : null;
    }
}
