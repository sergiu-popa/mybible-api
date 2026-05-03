<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\FinishOlympiadAttemptAction;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Http\Requests\Olympiad\FinishOlympiadAttemptRequest;
use App\Http\Resources\Olympiad\OlympiadAttemptResource;

final class FinishOlympiadAttemptController
{
    public function __invoke(
        FinishOlympiadAttemptRequest $request,
        OlympiadAttempt $attempt,
        FinishOlympiadAttemptAction $action,
    ): OlympiadAttemptResource {
        return OlympiadAttemptResource::make($action->handle($attempt));
    }
}
