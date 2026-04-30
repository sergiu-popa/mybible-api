<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domain\Admin\Users\Actions\CreateAdminUserAction;
use App\Http\Requests\Admin\Users\CreateAdminUserRequest;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\JsonResponse;

final class CreateAdminUserController
{
    public function __invoke(
        CreateAdminUserRequest $request,
        CreateAdminUserAction $action,
    ): JsonResponse {
        $user = $action->execute($request->toData());

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }
}
