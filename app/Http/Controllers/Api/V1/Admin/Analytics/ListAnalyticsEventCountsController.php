<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Actions\ListEventCountsAction;
use App\Http\Requests\Admin\Analytics\ListAnalyticsEventCountsRequest;
use App\Http\Resources\Analytics\AnalyticsEventCountRowResource;
use Illuminate\Http\JsonResponse;

final class ListAnalyticsEventCountsController
{
    public function __invoke(
        ListAnalyticsEventCountsRequest $request,
        ListEventCountsAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $rows = $action->execute($query);

        return response()->json([
            'data' => AnalyticsEventCountRowResource::collection($rows),
            'meta' => [
                'from' => $query->range->from->toIso8601String(),
                'to' => $query->range->to->toIso8601String(),
                'period' => $query->range->period,
                'event_type' => $query->eventType->value,
                'group_by' => $query->groupBy,
            ],
        ]);
    }
}
