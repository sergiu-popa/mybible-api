<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\StartOlympiadAttemptAction;
use App\Http\Requests\Olympiad\StartOlympiadAttemptRequest;
use App\Http\Resources\Olympiad\OlympiadAttemptStartResource;
use Illuminate\Http\JsonResponse;

final class StartOlympiadAttemptController
{
    public function __invoke(
        StartOlympiadAttemptRequest $request,
        StartOlympiadAttemptAction $action,
    ): JsonResponse {
        [$attempt, $uuids] = $action->handle($request->toData());

        return (new OlympiadAttemptStartResource($attempt, $uuids))
            ->response()
            ->setStatusCode(201);
    }
}
