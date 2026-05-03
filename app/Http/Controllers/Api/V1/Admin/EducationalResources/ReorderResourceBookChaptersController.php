<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\ReorderResourceBookChaptersAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\ReorderResourceBookChaptersRequest;
use Illuminate\Http\JsonResponse;

final class ReorderResourceBookChaptersController
{
    public function __invoke(
        ReorderResourceBookChaptersRequest $request,
        ResourceBook $book,
        ReorderResourceBookChaptersAction $action,
    ): JsonResponse {
        $action->execute($book, $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
