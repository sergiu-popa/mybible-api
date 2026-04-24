<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\User\Profile\Actions\DeleteUserAccountAction;
use App\Http\Requests\Profile\DeleteUserAccountRequest;
use App\Models\User;
use Illuminate\Http\Response;

final class DeleteUserAccountController
{
    public function __invoke(
        DeleteUserAccountRequest $request,
        DeleteUserAccountAction $action,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user, $request->toData());

        return response()->noContent();
    }
}
