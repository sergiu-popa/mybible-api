<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutCurrentTokenAction
{
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();

        /**
         * `currentAccessToken()` may return a `TransientToken` when the user
         * is authenticated via the session guard; that instance has no
         * database row to delete. Under `auth:sanctum` with bearer tokens it
         * is always a `PersonalAccessToken`.
         *
         * @phpstan-ignore instanceof.alwaysTrue
         */
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $token->delete();
    }
}
