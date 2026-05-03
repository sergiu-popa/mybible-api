<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\DeleteResourceBookChapterAction;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Http\Requests\Admin\EducationalResources\DeleteResourceBookChapterRequest;
use Illuminate\Http\Response;

final class DeleteResourceBookChapterController
{
    public function __invoke(
        DeleteResourceBookChapterRequest $request,
        ResourceBookChapter $chapter,
        DeleteResourceBookChapterAction $action,
    ): Response {
        $action->execute($chapter);

        return response()->noContent();
    }
}
