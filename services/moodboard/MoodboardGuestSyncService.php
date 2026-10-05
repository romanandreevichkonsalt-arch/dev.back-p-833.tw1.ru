<?php

namespace app\services\moodboard;

use app\models\GuestSession;
use app\models\Moodboard;
use app\models\User;
use app\services\guest\GuestSessionService;
use yii\web\BadRequestHttpException;

final class MoodboardGuestSyncService
{
    public function __construct(
        private readonly GuestSessionService $guestSessionService = new GuestSessionService(),
    ) {
    }

    /**
     * @return array{mergedCount: int, resultTotal: int}
     */
    public function sync(User $user, string $sessionId): array
    {
        $sessionId = $this->guestSessionService->normalize($sessionId);
        if ($sessionId === '') {
            throw new BadRequestHttpException('Поле sessionId обязательно.');
        }

        $userId = (int)$user->id;
        $guest = GuestSession::findOne(['session_id' => $sessionId]);
        $hasGuestBoards = Moodboard::find()->where(['author_session_id' => $sessionId])->exists();
        if ($guest !== null && $guest->moodboards_merged_at !== null && !$hasGuestBoards) {
            return [
                'mergedCount' => 0,
                'resultTotal' => (int)Moodboard::find()->where(['author_user_id' => $userId])->count(),
            ];
        }

        $merged = Moodboard::updateAll(
            [
                'author_user_id' => $userId,
                'author_session_id' => null,
            ],
            ['author_session_id' => $sessionId],
        );

        if ($guest !== null) {
            $guest->moodboards_merged_at = date('Y-m-d H:i:s');
            $guest->save(false, ['moodboards_merged_at', 'updated_at']);
        }

        return [
            'mergedCount' => (int)$merged,
            'resultTotal' => (int)Moodboard::find()->where(['author_user_id' => $userId])->count(),
        ];
    }
}
