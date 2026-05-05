<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commentary;

use App\Domain\Commentary\Actions\SubmitCommentaryErrorReportAction;
use App\Domain\Commentary\DataTransferObjects\SubmitCommentaryErrorReportData;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Commentary\SubmitCommentaryErrorReportRequest;
use App\Http\Resources\Commentary\CommentaryErrorReportResource;
use App\Support\Controllers\ResolvesTriggeringUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SubmitCommentaryErrorReportController
{
    use ResolvesTriggeringUser;

    public function __invoke(
        SubmitCommentaryErrorReportRequest $request,
        CommentaryText $text,
        SubmitCommentaryErrorReportAction $action,
    ): JsonResponse {
        $userId = $this->triggeringUserId($request);

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
