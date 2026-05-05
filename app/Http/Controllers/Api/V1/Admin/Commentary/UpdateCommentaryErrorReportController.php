<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\UpdateCommentaryErrorReportStatusAction;
use App\Domain\Commentary\DataTransferObjects\UpdateCommentaryErrorReportData;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Http\Requests\Admin\Commentary\UpdateCommentaryErrorReportRequest;
use App\Http\Resources\Commentary\AdminCommentaryErrorReportResource;
use App\Support\Controllers\ResolvesTriggeringUser;

final class UpdateCommentaryErrorReportController
{
    use ResolvesTriggeringUser;

    public function __invoke(
        UpdateCommentaryErrorReportRequest $request,
        CommentaryErrorReport $report,
        UpdateCommentaryErrorReportStatusAction $action,
    ): AdminCommentaryErrorReportResource {
        $userId = $this->triggeringUserId($request);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $updated = $action->execute(
            $report,
            UpdateCommentaryErrorReportData::from($validated, $userId),
        );

        return AdminCommentaryErrorReportResource::make($updated);
    }
}
