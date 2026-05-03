<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ShowCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Collections\ShowCollectionRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Collections
 */
final class ShowCollectionController
{
    public function __invoke(
        ShowCollectionRequest $request,
        Collection $collection,
        ShowCollectionAction $action,
    ): JsonResponse {
        return response()->json($action->handle($collection));
    }
}
