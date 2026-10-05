<?php

namespace app\services\guest;

use app\models\User;
use app\services\cart\CartService;
use app\services\favorites\FavoritesService;
use app\services\moodboard\MoodboardGuestSyncService;

class GuestDataSyncService
{
    public function __construct(
        private readonly FavoritesService $favoritesService = new FavoritesService(),
        private readonly CartService $cartService = new CartService(),
        private readonly MoodboardGuestSyncService $moodboardGuestSyncService = new MoodboardGuestSyncService(),
        private readonly GuestSessionService $guestSessionService = new GuestSessionService(),
    ) {
    }

    /**
     * @return array{
     *     skipped: bool,
     *     reason?: string,
     *     favorites?: array{mergedCount: int, resultTotal: int},
     *     cart?: array{mergedCount: int, resultTotal: int},
     *     moodboards?: array{mergedCount: int, resultTotal: int}
     * }
     */
    public function syncForCurrentRequest(User $user): array
    {
        return $this->syncForUser($user, $this->guestSessionService->resolveFromRequest());
    }

    /**
     * @return array{
     *     skipped: bool,
     *     reason?: string,
     *     favorites?: array{mergedCount: int, resultTotal: int},
     *     cart?: array{mergedCount: int, resultTotal: int},
     *     moodboards?: array{mergedCount: int, resultTotal: int}
     * }
     */
    public function syncForUser(User $user, string $sessionId): array
    {
        if ($sessionId === '') {
            return [
                'skipped' => true,
                'reason' => 'no_session',
            ];
        }

        return [
            'skipped' => false,
            'favorites' => $this->favoritesService->sync($user, $sessionId),
            'cart' => $this->cartService->sync($user, $sessionId),
            'moodboards' => $this->moodboardGuestSyncService->sync($user, $sessionId),
        ];
    }
}
