<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commentary;

use App\Domain\Commentary\Actions\SubmitCommentaryErrorReportAction;
use App\Domain\Commentary\DataTransferObjects\SubmitCommentaryErrorReportData;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Commentary\SubmitCommentaryErrorReportRequest;
use App\Http\Resources\Commentary\CommentaryErrorReportResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SubmitCommentaryErrorReportController
{
    public function __invoke(
        SubmitCommentaryErrorReportRequest $request,
        CommentaryText $text,
        SubmitCommentaryErrorReportAction $action,
    ): JsonResponse {
        $user = $request->user();
        $userId = $user instanceof User ? (int) $user->id : null;

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $report = $action->execute(SubmitCommentaryErrorReportData::from(
            $validated,
            (int) $text->id,
            $userId,
        ));

        return CommentaryErrorReportResource::make($report)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
