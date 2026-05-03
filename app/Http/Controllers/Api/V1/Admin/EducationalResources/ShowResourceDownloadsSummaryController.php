<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\Analytics\Actions\SummariseResourceDownloadsAction;
use App\Http\Requests\Admin\EducationalResources\ShowResourceDownloadsSummaryRequest;
use App\Http\Resources\Analytics\ResourceDownloadSummaryRowResource;
use Illuminate\Http\JsonResponse;

final class ShowResourceDownloadsSummaryController
{
    public function __invoke(
        ShowResourceDownloadsSummaryRequest $request,
        SummariseResourceDownloadsAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $rows = $action->execute($query);

        return response()->json([
            'data' => ResourceDownloadSummaryRowResource::collection($rows),
            'meta' => [
                'from' => $query->from->toIso8601String(),
                'to' => $query->to->toIso8601String(),
                'group_by' => $query->groupBy,
            ],
        ]);
    }
}
