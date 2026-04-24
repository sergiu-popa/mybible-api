<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\User\Profile\Actions\ChangeUserPasswordAction;
use App\Http\Requests\Profile\ChangeUserPasswordRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

final class ChangeUserPasswordController
{
    public function __invoke(
        ChangeUserPasswordRequest $request,
        ChangeUserPasswordAction $action,
    ): UserResource {
        /** @var User $user */
        $user = $request->user();

        $currentToken = $user->currentAccessToken();

        /**
         * Under the `auth:sanctum` bearer-token guard that protects this
         * route, `currentAccessToken()` always resolves to a
         * `PersonalAccessToken`. The runtime guard keeps PHPStan honest
         * while preserving a clear failure mode if the route is ever
         * mounted under a different guard.
         *
         * @phpstan-ignore instanceof.alwaysTrue
         */
        if (! $currentToken instanceof PersonalAccessToken) {
            throw new RuntimeException('Current access token is not a PersonalAccessToken.');
        }

        $action->execute($user, $request->toData(), $currentToken);

        return UserResource::make($user->refresh());
    }
}
