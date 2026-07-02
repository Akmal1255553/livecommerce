<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Services\CartServiceInterface;
use App\Events\UserAuthenticated;
use Throwable;

class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
    ) {}

    public function handle(UserAuthenticated $event): void
    {
        $guestToken = $event->guestCartToken;

        if ($guestToken === null || $guestToken === '') {
            return;
        }

        try {
            $this->cartService->mergeGuestIntoUser($guestToken, $event->user);
        } catch (Throwable $exception) {
            logger()->warning('Guest cart merge failed', [
                'user_id' => $event->user->id,
                'guest_token' => $guestToken,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
