<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminUsersController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $admins = User::query()
            ->whereJsonContains('roles', 'admin')
            ->orderBy('name')
            ->get();

        return UserResource::collection($admins);
    }
}
