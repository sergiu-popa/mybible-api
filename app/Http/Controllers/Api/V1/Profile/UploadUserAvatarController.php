<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\User\Profile\Actions\UploadUserAvatarAction;
use App\Http\Requests\Profile\UploadUserAvatarRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;

final class UploadUserAvatarController
{
    public function __invoke(
        UploadUserAvatarRequest $request,
        UploadUserAvatarAction $action,
    ): UserResource {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->execute($user, $request->toData());

        return UserResource::make($updated);
    }
}
