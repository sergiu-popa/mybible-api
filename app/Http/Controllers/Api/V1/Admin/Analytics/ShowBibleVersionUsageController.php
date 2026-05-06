<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Actions\SummariseBibleVersionUsageAction;
use App\Http\Requests\Admin\Analytics\ShowBibleVersionUsageRequest;
use App\Http\Resources\Analytics\BibleVersionUsageRowResource;
use Illuminate\Http\JsonResponse;

final class ShowBibleVersionUsageController
{
    public function __invoke(
        ShowBibleVersionUsageRequest $request,
        SummariseBibleVersionUsageAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $rows = $action->execute($query);

        return response()->json([
            'data' => BibleVersionUsageRowResource::collection($rows),
            'meta' => [
                'from' => $query->from->toIso8601String(),
                'to' => $query->to->toIso8601String(),
                'period' => $query->period,
            ],
        ]);
    }
}
