<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RegisterUserAction;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RegisterController
{
    public function __invoke(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $authToken = $action->execute($request->toData());

        return response()->json([
            'data' => [
                'user' => UserResource::make($authToken->user),
                'token' => $authToken->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }
}
