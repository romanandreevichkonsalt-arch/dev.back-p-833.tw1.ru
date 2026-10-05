<?php

namespace app\controllers\api\v1;

use app\models\User;
use app\services\guest\ApiOwnerContext;
use app\services\moodboard\MoodboardAuth;
use app\exceptions\ApiValidationException;
use app\services\moodboard\MoodboardBoardRequestParser;
use app\services\moodboard\MoodboardBoardService;
use app\services\moodboard\MoodboardCoverUploadService;
use app\services\moodboard\MoodboardGuestSyncService;
use app\services\moodboard\MoodboardPickerService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\IdentityInterface;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class MoodboardController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly MoodboardPickerService $pickerService = new MoodboardPickerService(),
        private readonly MoodboardBoardService $boardService = new MoodboardBoardService(),
        private readonly MoodboardGuestSyncService $guestSyncService = new MoodboardGuestSyncService(),
        private readonly MoodboardCoverUploadService $coverUploadService = new MoodboardCoverUploadService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['optional'] = [
            'picker-bootstrap',
            'picker-categories',
            'picker-colors',
            'picker-models',
            'picker-fabrics',
            'picker-surface-materials',
            'object-types',
            'view-public-board',
            'list-boards',
            'create-board',
            'view-board',
            'update-board',
            'patch-board',
            'delete-board',
            'upload-cover',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'picker-bootstrap' => ['GET', 'OPTIONS'],
            'picker-categories' => ['GET', 'OPTIONS'],
            'picker-colors' => ['GET', 'OPTIONS'],
            'picker-models' => ['GET', 'OPTIONS'],
            'picker-fabrics' => ['GET', 'OPTIONS'],
            'picker-surface-materials' => ['GET', 'OPTIONS'],
            'object-types' => ['GET', 'OPTIONS'],
            'list-boards' => ['GET', 'OPTIONS'],
            'create-board' => ['POST', 'OPTIONS'],
            'view-board' => ['GET', 'OPTIONS'],
            'update-board' => ['PUT', 'OPTIONS'],
            'patch-board' => ['PATCH', 'OPTIONS'],
            'delete-board' => ['DELETE', 'OPTIONS'],
            'view-public-board' => ['GET', 'OPTIONS'],
            'sync-boards' => ['POST', 'OPTIONS'],
            'upload-cover' => ['POST', 'OPTIONS'],
        ];
    }

    private function authenticatedUser(): ?User
    {
        $identity = Yii::$app->user->identity;

        return $identity instanceof User ? $identity : null;
    }

    private function requireGuestSessionId(): string
    {
        return ApiOwnerContext::resolve()->sessionId ?? '';
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/bootstrap",
     *     tags={"Мудборд"},
     *     summary="Стартовые данные редактора (категории, цвета, модели, ткани, материалы)",
     *     description="Один запрос вместо пяти picker-эндпoинтов при открытии страницы. Публичный GET без авторизации. Поля categories, colors, models, fabrics, surfaceMaterials — те же структуры, что у отдельных GET. objectTypes — список типов объектов канвы. Query limit (default 3) применяется к models, fabrics, surfaceMaterials; фильтры category, collection, color[], q и др. — как у соответствующих picker.",
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=3, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="collection", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="materialType", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Данные для первого экрана",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerBootstrapResponse")
     *     ),
     * )
     */
    public function actionPickerBootstrap(): array
    {
        return $this->pickerService->bootstrap();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/categories",
     *     tags={"Мудборд"},
     *     summary="Категории товаров для редактора мудборда",
     *     description="Публичный GET без авторизации.",
     *     @OA\Response(
     *         response=200,
     *         description="Список категорий",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerCategoriesResponse")
     *     )
     * )
     */
    public function actionPickerCategories(): array
    {
        return $this->pickerService->categories();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/colors",
     *     tags={"Мудборд"},
     *     summary="Цвета для фильтрации в редакторе мудборда",
     *     description="Публичный GET без авторизации.",
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Список цветов",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerColorsResponse")
     *     )
     * )
     */
    public function actionPickerColors(): array
    {
        return $this->pickerService->colors();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/models",
     *     tags={"Мудборд"},
     *     summary="Модели с ракурсами (сортировка A→Z внутри коллекции)",
     *     description="Публичный GET без авторизации.",
     *     @OA\Parameter(name="limit", in="query", required=false, description="Default 3, max 100", @OA\Schema(type="integer", default=3, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="slug коллекции мебели", @OA\Schema(type="string")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Модели",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerModelsResponse")
     *     )
     * )
     */
    public function actionPickerModels(): array
    {
        return $this->pickerService->models();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/fabrics",
     *     tags={"Мудборд"},
     *     summary="Ткани для редактора мудборда",
     *     description="Публичный GET без авторизации.",
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=3, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Ткани",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerFabricsResponse")
     *     )
     * )
     */
    public function actionPickerFabrics(): array
    {
        return $this->pickerService->fabrics();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/picker/surface-materials",
     *     tags={"Мудборд"},
     *     summary="Материалы (поверхности) для редактора мудборда",
     *     description="Публичный GET без авторизации.",
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=3, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="materialType", in="query", required=false, @OA\Schema(type="string", enum={"Дерево","Металл","Лак"})),
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Материалы",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardPickerSurfaceMaterialsResponse")
     *     )
     * )
     */
    public function actionPickerSurfaceMaterials(): array
    {
        return $this->pickerService->surfaceMaterials();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/object-types",
     *     tags={"Мудборд"},
     *     summary="Доступные типы объектов на канве мудборда",
     *     description="Публичный GET без авторизации.",
     *     @OA\Response(
     *         response=200,
     *         description="Типы объектов",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardObjectTypesResponse")
     *     )
     * )
     */
    public function actionObjectTypes(): array
    {
        return ['items' => $this->pickerService->objectTypes()];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/boards",
     *     tags={"Мудборд"},
     *     summary="Список мудбордов пользователя или гостевой сессии",
     *     description="Bearer — мудборды пользователя. Гость — X-Session-ID (или sessionId).",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=24, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Мудборды автора",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardListResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionListBoards(): array
    {
        $user = $this->authenticatedUser();
        if ($user !== null) {
            return $this->boardService->listForUser($user);
        }

        return $this->boardService->listForSession($this->requireGuestSessionId());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/moodboard/boards",
     *     tags={"Мудборд"},
     *     summary="Создать мудборд",
     *     description="Bearer — мудборд пользователя (без shareCode). Гость — X-Session-ID; в ответе shareCode, shareUrl, publicApiUrl. Обложка — поле cover в multipart/form-data вместе с title и JSON-полями items, comments, canvas (строки JSON). Без обложки можно application/json.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title"},
     *                 @OA\Property(property="title", type="string", maxLength=255),
     *                 @OA\Property(property="cover", type="string", format="binary", description="Файл обложки"),
     *                 @OA\Property(property="canvas", type="string", description="JSON MoodboardCanvas"),
     *                 @OA\Property(property="items", type="string", description="JSON-массив MoodboardSaveItem"),
     *                 @OA\Property(property="comments", type="string", description="JSON-массив MoodboardSaveComment")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/MoodboardCreateRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Созданный мудборд",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardDetailResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionCreateBoard(): array
    {
        $parsed = MoodboardBoardRequestParser::parse();
        $user = $this->authenticatedUser();
        if ($user !== null) {
            return $this->boardService->create($user, $parsed['payload'], $parsed['coverFile']);
        }

        return $this->boardService->createForSession(
            $this->requireGuestSessionId(),
            $parsed['payload'],
            $parsed['coverFile'],
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/boards/{id}",
     *     tags={"Мудборд"},
     *     summary="Получить мудборд владельца",
     *     description="Bearer — только свой user-мудборд. Гость — X-Session-ID и свой public_id.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="public_id мудборда", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Мудборд",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardDetailResponse")
     *     ),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionViewBoard(string $id): array
    {
        $user = $this->authenticatedUser();
        if ($user !== null) {
            return $this->boardService->getDetailForUser($id, $user);
        }

        return $this->boardService->getDetailForSession($id, $this->requireGuestSessionId());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/moodboard/public/{code}",
     *     tags={"Мудборд"},
     *     summary="Публичный просмотр мудборда по shareCode",
     *     description="Без авторизации. Доступен для гостевых мудбордов с public_share_enabled (ссылка не меняется после merge в аккаунт).",
     *     @OA\Parameter(name="code", in="path", required=true, description="shareCode из create/detail", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Мудборд",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardDetailResponse")
     *     ),
     *     @OA\Response(response=404, description="Не найден или шаринг отключён")
     * )
     */
    public function actionViewPublicBoard(string $code): array
    {
        return $this->boardService->getPublicDetail($code);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/moodboard/boards/{id}",
     *     tags={"Мудборд"},
     *     summary="Сохранить содержимое мудборда",
     *     description="Канва, объекты, комментарии и обложка (multipart cover). Название — только PATCH. Bearer или X-Session-ID владельца.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="public_id мудборда", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="cover", type="string", format="binary"),
     *                 @OA\Property(property="removeCover", type="string", description="1/true — удалить обложку"),
     *                 @OA\Property(property="canvas", type="string", description="JSON MoodboardCanvas"),
     *                 @OA\Property(property="items", type="string", description="JSON-массив MoodboardSaveItem"),
     *                 @OA\Property(property="comments", type="string", description="JSON-массив MoodboardSaveComment")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/MoodboardContentSaveRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Обновлённый мудборд",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardDetailResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации (лимиты items ≤200, comments ≤100)"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionUpdateBoard(string $id): array
    {
        $parsed = MoodboardBoardRequestParser::parse();
        $user = $this->authenticatedUser();
        if ($user !== null) {
            return $this->boardService->saveContent($id, $user, $parsed['payload'], $parsed['coverFile']);
        }

        return $this->boardService->saveContentForSession(
            $id,
            $this->requireGuestSessionId(),
            $parsed['payload'],
            $parsed['coverFile'],
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/moodboard/boards/{id}",
     *     tags={"Мудборд"},
     *     summary="Переименовать мудборд",
     *     description="Bearer или X-Session-ID владельца.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="public_id мудборда", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MoodboardPatchTitleRequest")),
     *     @OA\Response(
     *         response=200,
     *         description="Мудборд с новым названием",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardDetailResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionPatchBoard(string $id): array
    {
        $body = Yii::$app->request->bodyParams;
        $user = $this->authenticatedUser();
        if ($user !== null) {
            return $this->boardService->patchTitle($id, $user, $body);
        }

        return $this->boardService->patchTitleForSession($id, $this->requireGuestSessionId(), $body);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/moodboard/boards/{id}",
     *     tags={"Мудборд"},
     *     summary="Удалить мудборд",
     *     description="Bearer или X-Session-ID владельца.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Удалено"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionDeleteBoard(string $id): ?array
    {
        $user = $this->authenticatedUser();
        if ($user !== null) {
            $this->boardService->delete($id, $user);
        } else {
            $this->boardService->deleteForSession($id, $this->requireGuestSessionId());
        }
        Yii::$app->response->statusCode = 204;

        return null;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/moodboard/boards/sync",
     *     tags={"Мудборд"},
     *     summary="Объединить гостевые мудборды с аккаунтом",
     *     description="Bearer + sessionId (тело или X-Session-ID). Идемпотентен вместе с guestSync при входе.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/FavoritesSyncRequest")),
     *     @OA\Response(
     *         response=200,
     *         description="Результат merge",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardSyncResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSyncBoards(): array
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof IdentityInterface || !$identity instanceof User) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        $body = Yii::$app->request->bodyParams;
        $sessionId = trim((string)($body['sessionId'] ?? $body['session_id'] ?? Yii::$app->request->headers->get('X-Session-ID', '')));

        return $this->guestSyncService->sync($identity, $sessionId);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/moodboard/uploads/cover",
     *     tags={"Мудборд"},
     *     summary="Загрузить файл обложки (отдельный шаг перед save)",
     *     description="Bearer или X-Session-ID. В ответе mediaId — передайте в теле create/PUT как cover: { mediaId }. Альтернатива: файл cover в multipart create/PUT.",
     *     security={{"bearerAuth":{}}, {"sessionId":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="Предпочтительное имя поля"),
     *                 @OA\Property(property="cover", type="string", format="binary", description="Алиас для file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Файл загружен",
     *         @OA\JsonContent(ref="#/components/schemas/MoodboardCoverUploadResponse")
     *     ),
     *     @OA\Response(response=400, description="Нет файла или ошибка загрузки"),
     *     @OA\Response(response=401, description="Нет Bearer и X-Session-ID")
     * )
     */
    public function actionUploadCover(): array
    {
        if ($this->authenticatedUser() === null) {
            $sessionId = trim($this->requireGuestSessionId());
            if ($sessionId === '') {
                throw new UnauthorizedHttpException('Bearer token or X-Session-ID is required.');
            }
        }

        $file = UploadedFile::getInstanceByName('file');
        if ($file === null) {
            $file = UploadedFile::getInstanceByName('cover');
        }
        if ($file === null) {
            throw new ApiValidationException('Передайте файл в поле file.', [
                'file' => ['Обязательное поле.'],
            ]);
        }

        return $this->coverUploadService->upload($file);
    }
}
