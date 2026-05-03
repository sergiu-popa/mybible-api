<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\ReorderResourceBooksAction;
use App\Http\Requests\Admin\EducationalResources\ReorderResourceBooksRequest;
use Illuminate\Http\JsonResponse;

final class ReorderResourceBooksController
{
    public function __invoke(
        ReorderResourceBooksRequest $request,
        ReorderResourceBooksAction $action,
    ): JsonResponse {
        $action->execute($request->language(), $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
