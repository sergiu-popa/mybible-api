<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Actions\ShowResourceBookChapterAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Http\Requests\EducationalResources\ShowResourceBookChapterRequest;
use Illuminate\Http\JsonResponse;

final class ShowResourceBookChapterController
{
    public function __invoke(
        ShowResourceBookChapterRequest $request,
        ResourceBook $book,
        ResourceBookChapter $chapter,
        ShowResourceBookChapterAction $action,
    ): JsonResponse {
        return response()->json($action->execute($book, $chapter));
    }
}
