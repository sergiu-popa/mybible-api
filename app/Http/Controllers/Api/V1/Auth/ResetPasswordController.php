<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\ResetPasswordAction;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController
{
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $action,
    ): JsonResponse {
        $action->execute($request->toData());

        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }
}
