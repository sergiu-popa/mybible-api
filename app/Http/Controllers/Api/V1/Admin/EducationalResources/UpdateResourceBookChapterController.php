<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\UpdateResourceBookChapterAction;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Http\Requests\Admin\EducationalResources\UpdateResourceBookChapterRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookChapterResource;

final class UpdateResourceBookChapterController
{
    public function __invoke(
        UpdateResourceBookChapterRequest $request,
        ResourceBookChapter $chapter,
        UpdateResourceBookChapterAction $action,
    ): AdminResourceBookChapterResource {
        return AdminResourceBookChapterResource::make($action->execute($chapter, $request->changes()));
    }
}
