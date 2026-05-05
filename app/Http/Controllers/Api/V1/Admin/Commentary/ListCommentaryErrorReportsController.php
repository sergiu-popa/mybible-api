<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Http\Requests\Admin\Commentary\ListCommentaryErrorReportsRequest;
use App\Http\Resources\Commentary\AdminCommentaryErrorReportResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCommentaryErrorReportsController
{
    public function __invoke(ListCommentaryErrorReportsRequest $request): AnonymousResourceCollection
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $query = CommentaryErrorReport::query();

        if (isset($validated['status']) && is_string($validated['status'])) {
            $query->forStatus(CommentaryErrorReportStatus::from($validated['status']));
        }

        if (isset($validated['commentary_text_id'])) {
            $query->forCommentaryText((int) $validated['commentary_text_id']);
        }

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 25;

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return AdminCommentaryErrorReportResource::collection($paginator);
    }
}
