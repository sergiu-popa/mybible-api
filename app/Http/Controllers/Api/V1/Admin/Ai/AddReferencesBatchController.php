<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Ai;

use App\Application\Jobs\AddReferencesBatchJob;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Http\Requests\Admin\Ai\AddReferencesBatchRequest;
use App\Http\Resources\Admin\Imports\ImportJobResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;

final class AddReferencesBatchController
{
    public function __invoke(AddReferencesBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $importJob = ImportJob::query()->create([
            'type' => 'ai.add_references',
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [
                'subject_type' => (string) $validated['subject_type'],
                'subject_id' => (int) $validated['subject_id'],
                'language' => (string) $validated['language'],
                'filters' => is_array($validated['filters'] ?? null) ? $validated['filters'] : [],
            ],
            'user_id' => $user instanceof User ? (int) $user->id : null,
        ]);

        Bus::dispatch(new AddReferencesBatchJob(
            importJobId: (int) $importJob->id,
            subjectType: (string) $validated['subject_type'],
            subjectId: (int) $validated['subject_id'],
            language: (string) $validated['language'],
            filters: is_array($validated['filters'] ?? null) ? $validated['filters'] : [],
        ));

        return ImportJobResource::make($importJob)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
