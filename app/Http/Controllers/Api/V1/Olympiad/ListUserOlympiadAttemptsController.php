<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\ListUserOlympiadAttemptsAction;
use App\Http\Requests\Olympiad\ListUserOlympiadAttemptsRequest;
use App\Http\Resources\Olympiad\OlympiadAttemptResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUserOlympiadAttemptsController
{
    public function __invoke(
        ListUserOlympiadAttemptsRequest $request,
        ListUserOlympiadAttemptsAction $action,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();

        return OlympiadAttemptResource::collection(
            $action->handle($user, $request->filter(), $request->pageNumber(), $request->perPage()),
        );
    }
}
