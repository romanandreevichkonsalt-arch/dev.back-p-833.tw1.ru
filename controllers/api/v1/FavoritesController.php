<?php

namespace app\controllers\api\v1;

use app\exceptions\ApiValidationException;
use app\services\favorites\FavoritesService;
use app\services\guest\ApiOwnerContext;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\UnauthorizedHttpException;

class FavoritesController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly FavoritesService $favoritesService = new FavoritesService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['options'];
        $behaviors['authenticator']['optional'] = ['add', 'remove', 'check', 'list'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'add' => ['POST', 'OPTIONS'],
            'remove' => ['POST', 'OPTIONS'],
            'check' => ['POST', 'OPTIONS'],
            'list' => ['GET', 'OPTIONS'],
            'sync' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/favorites/add",
     *     tags={"Избранное"},
     *     summary="Добавить товар в избранное",
     *     description="Гость: заголовок X-Session-ID или sessionId в теле. Авторизованный: Bearer (запись привязывается к user_id).",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteProductRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Товар добавлен",
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteActionResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionAdd(): array
    {
        $owner = ApiOwnerContext::resolve();
        $productId = trim((string)(Yii::$app->request->bodyParams['productId'] ?? ''));

        return $this->favoritesService->add($owner, $productId);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/favorites/remove",
     *     tags={"Избранное"},
     *     summary="Удалить товар из избранного",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteProductRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Товар удалён",
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteActionResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionRemove(): array
    {
        $owner = ApiOwnerContext::resolve();
        $productId = trim((string)(Yii::$app->request->bodyParams['productId'] ?? ''));

        return $this->favoritesService->remove($owner, $productId);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/favorites/check",
     *     tags={"Избранное"},
     *     summary="Проверить наличие товаров в избранном",
     *     description="Возвращает map productId (slug) → isFavorite для переданного списка.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteCheckRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статусы избранного",
     *         @OA\JsonContent(ref="#/components/schemas/FavoriteCheckResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionCheck(): array
    {
        $owner = ApiOwnerContext::resolve();
        $productIds = Yii::$app->request->bodyParams['productIds'] ?? [];
        if (!is_array($productIds)) {
            throw new ApiValidationException('Ошибка валидации.', [
                'productIds' => ['productIds должен быть массивом.'],
            ]);
        }

        return $this->favoritesService->check($owner, $productIds);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/favorites/list",
     *     tags={"Избранное"},
     *     summary="Список избранного",
     *     description="Постраничный список. Поле product — карточка как в листинге каталога (CatalogProductCard).",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="pageSize", in="query", @OA\Schema(type="integer", default=50, maximum=200)),
     *     @OA\Parameter(name="page_size", in="query", description="Alias pageSize", @OA\Schema(type="integer", default=50, maximum=200)),
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="query",
     *         description="Для гостя, если не передан X-Session-ID",
     *         @OA\Schema(ref="#/components/schemas/GuestSessionId")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список избранного",
     *         @OA\JsonContent(ref="#/components/schemas/FavoritesListResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionList(): array
    {
        $owner = ApiOwnerContext::resolve();
        $page = (int)Yii::$app->request->get('page', 1);
        $pageSize = (int)(Yii::$app->request->get('pageSize') ?: Yii::$app->request->get('page_size', 50));

        return $this->favoritesService->list($owner, $page, $pageSize, $owner->identity());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/favorites/sync",
     *     tags={"Избранное"},
     *     summary="Объединить гостевое избранное с пользовательским",
     *     description="Ручной merge. Требует Bearer + sessionId (тело или X-Session-ID). Идемпотентен: безопасно вызывать после auth, даже если guestSync уже выполнен. Дубликаты не создаются.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/FavoritesSyncRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Результат объединения",
     *         @OA\JsonContent(ref="#/components/schemas/FavoritesSyncResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSync(): array
    {
        $user = Yii::$app->user->identity;
        if ($user === null || !($user instanceof \app\models\User)) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        $body = Yii::$app->request->bodyParams;
        $sessionId = trim((string)($body['sessionId'] ?? $body['session_id'] ?? Yii::$app->request->headers->get('X-Session-ID', '')));

        return $this->favoritesService->sync($user, $sessionId);
    }
}
