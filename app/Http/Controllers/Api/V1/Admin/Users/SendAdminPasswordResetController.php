<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Domain\Admin\Users\Actions\SendAdminPasswordResetAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class SendAdminPasswordResetController
{
    public function __invoke(User $user, SendAdminPasswordResetAction $action): JsonResponse
    {
        $action->execute($user);

        return response()->json([
            'message' => __('passwords.sent'),
        ]);
    }
}
