<?php

namespace tests\api;

use Codeception\Test\Unit;
use Yii;
use yii\web\Application;

abstract class ApiTestCase extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();
        Yii::$container->get(\app\services\cache\ApiResponseCache::class)->bump();
    }

    /**
     * @return array<string, mixed>
     */
    protected function postJson(string $route, array $body): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->request->setBodyParams($body);

        return Yii::$app->runAction($route);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJson(string $route, array $query = []): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Yii::$app->request->setQueryParams($query);

        return Yii::$app->runAction($route);
    }

    /**
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    protected function runAction(string $route, array $routeParams = [], array $body = [], string $method = 'GET'): ?array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        Yii::$app->request->setBodyParams($body);

        $result = Yii::$app->runAction($route, $routeParams);

        return is_array($result) ? $result : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function patchJson(string $route, array $body = [], array $routeParams = []): array
    {
        $result = $this->runAction($route, $routeParams, $body, 'PATCH');
        if (!is_array($result)) {
            throw new \RuntimeException('Expected array response from PATCH ' . $route);
        }

        return $result;
    }

    protected function deleteJson(string $route, array $routeParams = []): void
    {
        $this->runAction($route, $routeParams, [], 'DELETE');
    }

    protected function withSession(string $sessionId): void
    {
        Yii::$app->request->headers->set('X-Session-ID', $sessionId);
    }

    protected function headCatalog(array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        Yii::$app->request->setQueryParams($query);
        Yii::$app->runAction('api/v1/catalog/menu');
    }

    protected function getResponseHeader(string $name): ?string
    {
        return Yii::$app->response->headers->get($name);
    }

    protected function withBearer(string $token): void
    {
        Yii::$app->request->headers->set('Authorization', 'Bearer ' . $token);
    }
}
