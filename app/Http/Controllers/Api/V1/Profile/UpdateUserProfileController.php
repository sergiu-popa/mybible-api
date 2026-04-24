<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\User\Profile\Actions\UpdateUserProfileAction;
use App\Http\Requests\Profile\UpdateUserProfileRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;

final class UpdateUserProfileController
{
    public function __invoke(
        UpdateUserProfileRequest $request,
        UpdateUserProfileAction $action,
    ): UserResource {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->execute($user, $request->toData());

        return UserResource::make($updated);
    }
}
