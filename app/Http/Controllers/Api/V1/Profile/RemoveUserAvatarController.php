<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\User\Profile\Actions\RemoveUserAvatarAction;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

final class RemoveUserAvatarController
{
    public function __invoke(
        Request $request,
        RemoveUserAvatarAction $action,
    ): UserResource {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->execute($user);

        return UserResource::make($updated);
    }
}
