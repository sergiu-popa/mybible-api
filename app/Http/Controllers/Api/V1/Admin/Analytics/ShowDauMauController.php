<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Actions\ComputeDauMauAction;
use App\Http\Requests\Admin\Analytics\ShowDauMauRequest;
use App\Http\Resources\Analytics\DauMauRowResource;
use Illuminate\Http\JsonResponse;

final class ShowDauMauController
{
    public function __invoke(
        ShowDauMauRequest $request,
        ComputeDauMauAction $action,
    ): JsonResponse {
        $query = $request->toData();
        $rows = $action->execute($query);

        return response()->json([
            'data' => DauMauRowResource::collection($rows),
            'meta' => [
                'from' => $query->from->toIso8601String(),
                'to' => $query->to->toIso8601String(),
                'period' => $query->period,
            ],
        ]);
    }
}
