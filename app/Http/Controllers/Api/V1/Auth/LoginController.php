<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\LoginUserAction;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __invoke(LoginUserRequest $request, LoginUserAction $action): JsonResponse
    {
        $authToken = $action->execute($request->toData());

        return response()->json([
            'data' => [
                'user' => UserResource::make($authToken->user),
                'token' => $authToken->plainTextToken,
            ],
        ]);
    }
}
