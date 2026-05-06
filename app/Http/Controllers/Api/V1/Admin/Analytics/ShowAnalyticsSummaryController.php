<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Actions\SummariseAnalyticsAction;
use App\Http\Requests\Admin\Analytics\ShowAnalyticsSummaryRequest;
use App\Http\Resources\Analytics\AnalyticsSummaryResource;
use Illuminate\Http\JsonResponse;

final class ShowAnalyticsSummaryController
{
    public function __invoke(
        ShowAnalyticsSummaryRequest $request,
        SummariseAnalyticsAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $summary = $action->execute($query);

        return response()->json([
            'data' => new AnalyticsSummaryResource($summary),
            'meta' => [
                'from' => $query->from->toIso8601String(),
                'to' => $query->to->toIso8601String(),
                'period' => $query->period,
            ],
        ]);
    }
}
