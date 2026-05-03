<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Actions\ShowResourceBookAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use Illuminate\Http\JsonResponse;

final class ShowResourceBookController
{
    public function __invoke(
        ResourceBook $book,
        ShowResourceBookAction $action,
    ): JsonResponse {
        return response()->json($action->execute($book));
    }
}
