<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\UpdateSegmentContentAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Http\Requests\Admin\SabbathSchool\UpdateSegmentContentRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolSegmentContentResource;

final class UpdateSegmentContentController
{
    public function __invoke(
        UpdateSegmentContentRequest $request,
        SabbathSchoolSegmentContent $content,
        UpdateSegmentContentAction $action,
    ): SabbathSchoolSegmentContentResource {
        return SabbathSchoolSegmentContentResource::make(
            $action->execute($content, $request->toData()),
        );
    }
}
