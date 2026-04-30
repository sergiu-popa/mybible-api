<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domain\Admin\Users\Actions\DisableAdminUserAction;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;

final class DisableAdminUserController
{
    public function __invoke(User $user, DisableAdminUserAction $action): UserResource
    {
        return UserResource::make($action->execute($user));
    }
}
