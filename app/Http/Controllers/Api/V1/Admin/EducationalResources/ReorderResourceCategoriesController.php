<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\ReorderResourceCategoriesAction;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Http\JsonResponse;

final class ReorderResourceCategoriesController
{
    public function __invoke(
        ReorderRequest $request,
        ReorderResourceCategoriesAction $action,
    ): JsonResponse {
        $action->execute($request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
