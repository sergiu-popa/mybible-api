<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RequestPasswordResetAction;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use Illuminate\Http\JsonResponse;

final class RequestPasswordResetController
{
    public function __invoke(
        RequestPasswordResetRequest $request,
        RequestPasswordResetAction $action,
    ): JsonResponse {
        $action->execute($request->toData());

        return response()->json([
            'message' => __('passwords.sent'),
        ]);
    }
}
