<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\CreateSegmentContentAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Http\Requests\Admin\SabbathSchool\CreateSegmentContentRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolSegmentContentResource;
use Illuminate\Http\JsonResponse;

final class CreateSegmentContentController
{
    public function __invoke(
        CreateSegmentContentRequest $request,
        SabbathSchoolSegment $segment,
        CreateSegmentContentAction $action,
    ): JsonResponse {
        $content = $action->execute($segment, $request->toData());

        return SabbathSchoolSegmentContentResource::make($content)
            ->response()
            ->setStatusCode(201);
    }
}
