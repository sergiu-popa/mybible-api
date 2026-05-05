<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Application\Jobs\ExportCommentarySqliteJob;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\ExportCommentarySqliteRequest;
use App\Http\Resources\Admin\Imports\ImportJobResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;

final class ExportCommentarySqliteController
{
    public function __invoke(
        ExportCommentarySqliteRequest $request,
        Commentary $commentary,
    ): JsonResponse {
        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->id : null;

        $importJob = ImportJob::query()->create([
            'type' => 'commentary.sqlite_export',
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [
                'commentary_id' => (int) $commentary->id,
                'commentary_slug' => (string) $commentary->slug,
            ],
            'user_id' => $userId,
        ]);

        Bus::dispatch(new ExportCommentarySqliteJob(
            importJobId: (int) $importJob->id,
            commentaryId: (int) $commentary->id,
        ));

        return ImportJobResource::make($importJob)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
