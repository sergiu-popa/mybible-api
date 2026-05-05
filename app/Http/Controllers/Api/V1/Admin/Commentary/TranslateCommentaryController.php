<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Application\Jobs\TranslateCommentaryJob;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Actions\TranslateCommentaryAction;
use App\Domain\Commentary\DataTransferObjects\TranslateCommentaryData;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\TranslateCommentaryRequest;
use App\Http\Resources\Admin\Imports\ImportJobResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;

final class TranslateCommentaryController
{
    public function __invoke(
        TranslateCommentaryRequest $request,
        Commentary $commentary,
        TranslateCommentaryAction $action,
    ): JsonResponse {
        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->id : null;

        $target = $action->prepare(new TranslateCommentaryData(
            sourceCommentaryId: (int) $commentary->id,
            targetLanguage: $request->targetLanguage(),
            overwrite: $request->overwrite(),
            triggeredByUserId: $userId,
        ));

        $importJob = ImportJob::query()->create([
            'type' => 'commentary.translate',
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [
                'source_commentary_id' => (int) $commentary->id,
                'target_commentary_id' => (int) $target->id,
                'target_language' => $request->targetLanguage(),
                'overwrite' => $request->overwrite(),
            ],
            'user_id' => $userId,
        ]);

        Bus::dispatch(new TranslateCommentaryJob(
            importJobId: (int) $importJob->id,
            sourceCommentaryId: (int) $commentary->id,
            targetCommentaryId: (int) $target->id,
            triggeredByUserId: $userId,
        ));

        return ImportJobResource::make($importJob)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
