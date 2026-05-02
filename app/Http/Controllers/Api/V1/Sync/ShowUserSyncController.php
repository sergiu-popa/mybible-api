<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sync;

use App\Domain\Sync\Actions\ShowUserSyncAction;
use App\Http\Requests\Sync\ShowUserSyncRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sync
 */
final class ShowUserSyncController
{
    public function __invoke(ShowUserSyncRequest $request, ShowUserSyncAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute((int) auth()->id(), $request->since()),
        ]);
    }
}
