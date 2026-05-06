<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Actions\BuildReadingPlanFunnelAction;
use App\Http\Requests\Admin\Analytics\ShowReadingPlanFunnelRequest;
use App\Http\Resources\Analytics\ReadingPlanFunnelResource;
use Illuminate\Http\JsonResponse;

final class ShowReadingPlanFunnelController
{
    public function __invoke(
        ShowReadingPlanFunnelRequest $request,
        BuildReadingPlanFunnelAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $funnel = $action->execute($query);

        return response()->json([
            'data' => new ReadingPlanFunnelResource($funnel),
            'meta' => [
                'from' => $query->range->from->toIso8601String(),
                'to' => $query->range->to->toIso8601String(),
                'period' => $query->range->period,
                'plan_id' => $query->planId,
            ],
        ]);
    }
}
