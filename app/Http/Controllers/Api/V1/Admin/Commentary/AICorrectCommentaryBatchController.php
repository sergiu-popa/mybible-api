<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Application\Jobs\CorrectCommentaryBatchJob;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\AICommentaryBatchRequest;
use App\Http\Resources\Admin\Imports\ImportJobResource;
use App\Support\Controllers\ResolvesTriggeringUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;

final class AICorrectCommentaryBatchController
{
    use ResolvesTriggeringUser;

    public function __invoke(
        AICommentaryBatchRequest $request,
        Commentary $commentary,
    ): JsonResponse {
        $userId = $this->triggeringUserId($request);
        $filters = $request->filters();

        $importJob = ImportJob::query()->create([
            'type' => 'commentary.ai_correct',
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [
                'commentary_id' => (int) $commentary->id,
                'filters' => $filters,
            ],
            'user_id' => $userId,
        ]);

        Bus::dispatch(new CorrectCommentaryBatchJob(
            importJobId: (int) $importJob->id,
            commentaryId: (int) $commentary->id,
            filters: $filters,
            triggeredByUserId: $userId,
        ));

        return ImportJobResource::make($importJob)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
