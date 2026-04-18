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
         * `currentAccessToken()` may return `null` (unauthenticated caller) or
         * a `TransientToken` (session-guard authentication) — neither has a
         * database row to delete. Under `auth:sanctum` with bearer tokens it
         * is always a `PersonalAccessToken`. The `instanceof` guard covers
         * both non-deletable cases.
         *
         * @phpstan-ignore instanceof.alwaysTrue
         */
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $token->delete();
    }
}
