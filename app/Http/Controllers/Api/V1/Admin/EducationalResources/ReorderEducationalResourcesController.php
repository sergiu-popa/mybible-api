<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\ReorderEducationalResourcesAction;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Http\JsonResponse;

final class ReorderEducationalResourcesController
{
    public function __invoke(
        ReorderRequest $request,
        ResourceCategory $category,
        ReorderEducationalResourcesAction $action,
    ): JsonResponse {
        $action->execute($category, $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
