<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\LogoutCurrentTokenAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LogoutController
{
    public function __invoke(Request $request, LogoutCurrentTokenAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return response()->noContent();
    }
}
