<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\CreateResourceBookChapterAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\CreateResourceBookChapterRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookChapterResource;
use Illuminate\Http\JsonResponse;

final class CreateResourceBookChapterController
{
    public function __invoke(
        CreateResourceBookChapterRequest $request,
        ResourceBook $book,
        CreateResourceBookChapterAction $action,
    ): JsonResponse {
        $chapter = $action->execute($book, $request->toData());

        return AdminResourceBookChapterResource::make($chapter)
            ->response()
            ->setStatusCode(201);
    }
}
