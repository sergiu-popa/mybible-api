<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\SubmitOlympiadAttemptAnswersAction;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Http\Requests\Olympiad\SubmitOlympiadAttemptAnswersRequest;
use App\Http\Resources\Olympiad\OlympiadAttemptResource;

final class SubmitOlympiadAttemptAnswersController
{
    public function __invoke(
        SubmitOlympiadAttemptAnswersRequest $request,
        OlympiadAttempt $attempt,
        SubmitOlympiadAttemptAnswersAction $action,
    ): OlympiadAttemptResource {
        $action->handle($request->toData());

        return OlympiadAttemptResource::make($attempt->fresh() ?? $attempt);
    }
}
