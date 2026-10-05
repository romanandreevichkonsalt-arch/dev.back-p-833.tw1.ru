<?php

namespace app\services\moodboard;

use app\exceptions\ApiValidationException;
use app\models\MediaFile;
use app\models\Moodboard;
use app\models\MoodboardComment;
use app\models\MoodboardItem;
use app\models\User;
use app\services\guest\GuestSessionService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class MoodboardBoardService
{
    private const MAX_ITEMS = 200;
    private const MAX_COMMENTS = 100;

    public function __construct(
        private readonly MoodboardItemRefResolver $refResolver = new MoodboardItemRefResolver(),
        private readonly MoodboardItemEnricher $itemEnricher = new MoodboardItemEnricher(),
        private readonly MoodboardCoverUploadService $coverUpload = new MoodboardCoverUploadService(),
        private readonly GuestSessionService $guestSessionService = new GuestSessionService(),
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function listForUser(User $user): array
    {
        $pagination = MoodboardPagination::fromRequest(24);

        $query = Moodboard::find()
            ->with(['coverMedia'])
            ->where(['author_user_id' => (int)$user->getId()])
            ->orderBy(['updated_at' => SORT_DESC]);

        $total = (int)$query->count();

        $boards = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $items = [];
        foreach ($boards as $board) {
            $items[] = $this->toListPayload($board);
        }

        return [
            'items' => $items,
            'meta' => MoodboardPagination::meta($total, $pagination['page'], $pagination['limit']),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function listForSession(string $sessionId): array
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $pagination = MoodboardPagination::fromRequest(24);

        $query = Moodboard::find()
            ->with(['coverMedia'])
            ->where(['author_session_id' => $sessionId])
            ->orderBy(['updated_at' => SORT_DESC]);

        $total = (int)$query->count();

        $boards = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $items = [];
        foreach ($boards as $board) {
            $items[] = $this->toListPayload($board);
        }

        return [
            'items' => $items,
            'meta' => MoodboardPagination::meta($total, $pagination['page'], $pagination['limit']),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(User $user, array $body, ?UploadedFile $coverFile = null): array
    {
        $title = $this->requireTitle($body);

        $board = new Moodboard();
        $board->public_id = Moodboard::generatePublicId();
        $board->author_user_id = (int)$user->getId();
        $board->author_session_id = null;
        $board->title = $title;
        $board->status = Moodboard::STATUS_DRAFT;
        $board->public_share_enabled = false;
        $board->share_code = null;
        $this->applyCover($board, $coverFile, $body);
        $this->applyCanvasFromBody($board, $body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось создать мудборд.', $board->getErrors());
        }

        $this->replaceItemsAndComments($board, $body, $user);

        return $this->getDetailForUser($board->public_id, $user);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function createForSession(string $sessionId, array $body, ?UploadedFile $coverFile = null): array
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $this->guestSessionService->ensure($sessionId);
        $title = $this->requireTitle($body);

        $board = new Moodboard();
        $board->public_id = Moodboard::generatePublicId();
        $board->author_user_id = null;
        $board->author_session_id = $sessionId;
        $board->title = $title;
        $board->status = Moodboard::STATUS_DRAFT;
        $board->public_share_enabled = true;
        $board->share_code = Moodboard::generateShareCode();
        $this->applyCover($board, $coverFile, $body);
        $this->applyCanvasFromBody($board, $body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось создать мудборд.', $board->getErrors());
        }

        $this->replaceItemsAndComments($board, $body, null);

        return $this->getDetailForSession($board->public_id, $sessionId);
    }

    /**
     * Сохранить содержимое мудборда (канва, объекты, комментарии, обложка). Название — только PATCH.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function saveContent(string $publicId, User $user, array $body, ?UploadedFile $coverFile = null): array
    {
        $board = $this->findOwnedBoard($publicId, $user);

        $this->applyCover($board, $coverFile, $body);
        $this->applyCanvasFromBody($board, $body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось сохранить мудборд.', $board->getErrors());
        }

        if (array_key_exists('items', $body) || array_key_exists('comments', $body)) {
            $this->replaceItemsAndComments($board, $body, $user);
        }

        return $this->getDetailForUser($board->public_id, $user);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function saveContentForSession(
        string $publicId,
        string $sessionId,
        array $body,
        ?UploadedFile $coverFile = null,
    ): array {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $board = $this->findOwnedBoardBySession($publicId, $sessionId);

        $this->applyCover($board, $coverFile, $body);
        $this->applyCanvasFromBody($board, $body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось сохранить мудборд.', $board->getErrors());
        }

        if (array_key_exists('items', $body) || array_key_exists('comments', $body)) {
            $this->replaceItemsAndComments($board, $body, null);
        }

        return $this->getDetailForSession($board->public_id, $sessionId);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function patchTitle(string $publicId, User $user, array $body): array
    {
        $board = $this->findOwnedBoard($publicId, $user);
        $board->title = $this->requireTitle($body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось сохранить название.', $board->getErrors());
        }

        return $this->getDetailForUser($board->public_id, $user);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function patchTitleForSession(string $publicId, string $sessionId, array $body): array
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $board = $this->findOwnedBoardBySession($publicId, $sessionId);
        $board->title = $this->requireTitle($body);

        if (!$board->save()) {
            throw new ApiValidationException('Не удалось сохранить название.', $board->getErrors());
        }

        return $this->getDetailForSession($board->public_id, $sessionId);
    }

    public function delete(string $publicId, User $user): void
    {
        $board = $this->findOwnedBoard($publicId, $user);
        $board->delete();
    }

    public function deleteForSession(string $publicId, string $sessionId): void
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $board = $this->findOwnedBoardBySession($publicId, $sessionId);
        $board->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicDetail(string $shareCode): array
    {
        $shareCode = trim($shareCode);
        if ($shareCode === '') {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        $board = Moodboard::find()
            ->with(['coverMedia', 'author', 'items', 'comments'])
            ->where([
                'share_code' => $shareCode,
                'public_share_enabled' => true,
            ])
            ->one();

        if ($board === null) {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        return $this->toDetailPayload($board, $board->author);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailForUser(string $publicId, User $user): array
    {
        $board = Moodboard::find()
            ->with(['coverMedia', 'author', 'items', 'comments'])
            ->where([
                'public_id' => $publicId,
                'author_user_id' => (int)$user->getId(),
            ])
            ->one();

        if ($board === null) {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        return $this->toDetailPayload($board, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailForSession(string $publicId, string $sessionId): array
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        $board = Moodboard::find()
            ->with(['coverMedia', 'items', 'comments'])
            ->where([
                'public_id' => $publicId,
                'author_session_id' => $sessionId,
            ])
            ->one();

        if ($board === null) {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        return $this->toDetailPayload($board, null);
    }

    private function findOwnedBoard(string $publicId, User $user): Moodboard
    {
        $board = Moodboard::find()
            ->where([
                'public_id' => $publicId,
                'author_user_id' => (int)$user->getId(),
            ])
            ->one();

        if ($board === null) {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        return $board;
    }

    private function findOwnedBoardBySession(string $publicId, string $sessionId): Moodboard
    {
        $board = Moodboard::find()
            ->where([
                'public_id' => $publicId,
                'author_session_id' => $sessionId,
            ])
            ->one();

        if ($board === null) {
            throw new NotFoundHttpException('Мудборд не найден.');
        }

        return $board;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyCover(Moodboard $board, ?UploadedFile $coverFile, array $body): void
    {
        if ($coverFile !== null) {
            $uploaded = $this->coverUpload->upload($coverFile);
            $board->cover_media_id = (int)$uploaded['mediaId'];

            return;
        }

        if ($this->isTruthy($body['removeCover'] ?? false)) {
            $board->cover_media_id = null;

            return;
        }

        $this->applyCoverMediaIdFromBody($board, $body);
    }

    /**
     * Обложка после POST /moodboard/uploads/cover: cover: { mediaId } в JSON-теле save/create.
     *
     * @param array<string, mixed> $body
     */
    private function applyCoverMediaIdFromBody(Moodboard $board, array $body): void
    {
        if (!array_key_exists('cover', $body)) {
            return;
        }

        $cover = $body['cover'];
        if ($cover === null) {
            $board->cover_media_id = null;

            return;
        }

        if (!is_array($cover) || !array_key_exists('mediaId', $cover)) {
            return;
        }

        $mediaId = $cover['mediaId'];
        if ($mediaId === null || $mediaId === '') {
            $board->cover_media_id = null;

            return;
        }

        $media = MediaFile::findOne((int)$mediaId);
        if ($media === null) {
            throw new ApiValidationException('Обложка не найдена.', [
                'cover.mediaId' => ['Файл медиатеки не найден.'],
            ]);
        }

        $board->cover_media_id = (int)$media->id;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyCanvasFromBody(Moodboard $board, array $body): void
    {
        if (isset($body['canvas']) && is_array($body['canvas'])) {
            $width = $body['canvas']['width'] ?? null;
            $height = $body['canvas']['height'] ?? null;
            $board->canvas_width = is_numeric($width) ? (int)$width : null;
            $board->canvas_height = is_numeric($height) ? (int)$height : null;
        }
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function replaceItemsAndComments(Moodboard $board, array $body, ?User $user): void
    {
        $items = $body['items'] ?? [];
        $comments = $body['comments'] ?? [];

        if (!is_array($items)) {
            throw new ApiValidationException('Некорректный формат items.');
        }
        if (!is_array($comments)) {
            throw new ApiValidationException('Некорректный формат comments.');
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new ApiValidationException('Слишком много объектов на мудборде.', [
                'items' => ['Максимум ' . self::MAX_ITEMS . ' объектов.'],
            ]);
        }

        if (count($comments) > self::MAX_COMMENTS) {
            throw new ApiValidationException('Слишком много комментариев.', [
                'comments' => ['Максимум ' . self::MAX_COMMENTS . ' комментариев.'],
            ]);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            MoodboardItem::deleteAll(['moodboard_id' => (int)$board->id]);
            MoodboardComment::deleteAll(['moodboard_id' => (int)$board->id]);

            $sortOrder = 0;
            foreach ($items as $index => $rawItem) {
                if (!is_array($rawItem)) {
                    throw new ApiValidationException('Некорректный элемент items.', [
                        "items.$index" => ['Ожидается объект.'],
                    ]);
                }
                $this->insertItem($board, $rawItem, $sortOrder++);
            }

            foreach ($comments as $index => $rawComment) {
                if (!is_array($rawComment)) {
                    throw new ApiValidationException('Некорректный элемент comments.', [
                        "comments.$index" => ['Ожидается объект.'],
                    ]);
                }
                $this->insertComment($board, $rawComment, $user);
            }

            $board->touch('updated_at');
            $board->save(false, ['updated_at']);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function insertItem(Moodboard $board, array $rawItem, int $sortOrder): void
    {
        $objectType = trim((string)($rawItem['objectType'] ?? ''));
        $geometry = $rawItem['geometry'] ?? null;
        if (!is_array($geometry)) {
            throw new ApiValidationException('Укажите geometry для объекта.', [
                'geometry' => ['Обязательное поле.'],
            ]);
        }

        $resolved = $this->refResolver->resolve(
            $objectType,
            $rawItem['refSlug'] ?? null,
            $rawItem['refId'] ?? null,
        );

        $width = $this->requireGeometryNumber($geometry, 'width');
        $height = $this->requireGeometryNumber($geometry, 'height');
        $x = $this->requireGeometryNumber($geometry, 'x');
        $y = $this->requireGeometryNumber($geometry, 'y');
        $zIndex = isset($geometry['zIndex']) && is_numeric($geometry['zIndex']) ? (int)$geometry['zIndex'] : 0;
        $rotation = isset($geometry['rotation']) && is_numeric($geometry['rotation']) ? (float)$geometry['rotation'] : 0.0;

        $meta = $rawItem['meta'] ?? null;
        $payload = is_array($meta) ? $meta : null;

        $item = new MoodboardItem();
        $item->moodboard_id = (int)$board->id;
        $item->object_type = $objectType;
        $item->ref_id = $resolved['refId'];
        $item->ref_slug = $resolved['refSlug'];
        $item->width = $width;
        $item->height = $height;
        $item->x = $x;
        $item->y = $y;
        $item->z_index = $zIndex;
        $item->rotation = $rotation;
        $item->sort_order = $sortOrder;
        $item->created_at = date('Y-m-d H:i:s');
        $item->setPayloadArray($payload);

        if (!$item->save()) {
            throw new ApiValidationException('Не удалось сохранить объект мудборда.', $item->getErrors());
        }
    }

    /**
     * @param array<string, mixed> $rawComment
     */
    private function insertComment(Moodboard $board, array $rawComment, ?User $user): void
    {
        $text = trim((string)($rawComment['text'] ?? ''));
        if ($text === '') {
            throw new ApiValidationException('Текст комментария обязателен.', [
                'text' => ['Комментарий не может быть пустым.'],
            ]);
        }

        if (!isset($rawComment['x'], $rawComment['y']) || !is_numeric($rawComment['x']) || !is_numeric($rawComment['y'])) {
            throw new ApiValidationException('Укажите координаты комментария.', [
                'x' => ['Обязательное число.'],
                'y' => ['Обязательное число.'],
            ]);
        }

        $comment = new MoodboardComment();
        $comment->moodboard_id = (int)$board->id;
        $comment->text = mb_substr($text, 0, 2000);
        $comment->x = (float)$rawComment['x'];
        $comment->y = (float)$rawComment['y'];
        $comment->author_user_id = $user !== null ? (int)$user->getId() : null;
        $comment->created_at = date('Y-m-d H:i:s');

        if (!$comment->save()) {
            throw new ApiValidationException('Не удалось сохранить комментарий.', $comment->getErrors());
        }
    }

    /**
     * @param array<string, mixed> $geometry
     */
    private function requireGeometryNumber(array $geometry, string $key): float
    {
        if (!isset($geometry[$key]) || !is_numeric($geometry[$key])) {
            throw new ApiValidationException('Некорректная geometry.', [
                "geometry.$key" => ['Обязательное число.'],
            ]);
        }

        $value = (float)$geometry[$key];
        if ($value < 0) {
            throw new ApiValidationException('Geometry не может быть отрицательной.', [
                "geometry.$key" => ['Значение должно быть ≥ 0.'],
            ]);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireTitle(array $body): string
    {
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') {
            throw new ApiValidationException('Укажите название мудборда.', [
                'title' => ['Обязательное поле.'],
            ]);
        }

        if (mb_strlen($title) > 255) {
            throw new ApiValidationException('Слишком длинное название.', [
                'title' => ['Не более 255 символов.'],
            ]);
        }

        return $title;
    }

    /**
     * @return array<string, mixed>
     */
    private function toListPayload(Moodboard $board): array
    {
        $cover = null;
        if ($board->coverMedia !== null) {
            $cover = $board->coverMedia->toApiImagePayload($board->title);
        }

        return array_merge(
            [
                'id' => $board->public_id,
                'title' => $board->title,
                'status' => $board->status,
                'cover' => $cover,
                'updatedAt' => $board->updated_at,
                'createdAt' => $board->created_at,
            ],
            MoodboardShareUrls::sharePayload($board),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailPayload(Moodboard $board, ?User $authorUser): array
    {
        $cover = null;
        if ($board->coverMedia !== null) {
            $cover = $board->coverMedia->toApiImagePayload($board->title);
        }

        $canvas = null;
        if ($board->canvas_width !== null || $board->canvas_height !== null) {
            $canvas = [
                'width' => $board->canvas_width,
                'height' => $board->canvas_height,
            ];
        }

        $items = [];
        foreach ($board->items as $item) {
            $geometry = [
                'width' => (float)$item->width,
                'height' => (float)$item->height,
                'x' => (float)$item->x,
                'y' => (float)$item->y,
                'zIndex' => (int)$item->z_index,
                'rotation' => (float)$item->rotation,
            ];

            $payload = [
                'id' => (int)$item->id,
                'objectType' => $item->object_type,
                'refSlug' => $item->ref_slug,
                'refId' => $item->ref_id,
                'geometry' => $geometry,
                'ref' => $this->itemEnricher->enrichRef($item),
            ];

            $meta = $item->getPayloadArray();
            if ($meta !== null) {
                $payload['meta'] = $meta;
            }

            $items[] = $payload;
        }

        $comments = [];
        foreach ($board->comments as $comment) {
            $comments[] = [
                'id' => (int)$comment->id,
                'text' => $comment->text,
                'x' => (float)$comment->x,
                'y' => (float)$comment->y,
                'createdAt' => $comment->created_at,
            ];
        }

        $author = null;
        if ($authorUser !== null) {
            $author = MoodboardAuth::authorPayload($authorUser);
        } elseif ($board->author !== null) {
            $author = MoodboardAuth::authorPayload($board->author);
        }

        return array_merge(
            [
                'id' => $board->public_id,
                'title' => $board->title,
                'status' => $board->status,
                'author' => $author,
                'cover' => $cover,
                'canvas' => $canvas,
                'items' => $items,
                'comments' => $comments,
                'createdAt' => $board->created_at,
                'updatedAt' => $board->updated_at,
            ],
            MoodboardShareUrls::sharePayload($board),
        );
    }
}
