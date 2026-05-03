<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Actions\ReorderDevotionalTypesAction;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Http\JsonResponse;

final class ReorderDevotionalTypesController
{
    public function __invoke(
        ReorderRequest $request,
        ReorderDevotionalTypesAction $action,
    ): JsonResponse {
        $action->handle($request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
