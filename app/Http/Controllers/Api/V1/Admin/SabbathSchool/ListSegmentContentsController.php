<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Http\Requests\Admin\SabbathSchool\ListSegmentContentsRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolSegmentContentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListSegmentContentsController
{
    public function __invoke(
        ListSegmentContentsRequest $request,
        SabbathSchoolSegment $segment,
    ): AnonymousResourceCollection {
        return SabbathSchoolSegmentContentResource::collection(
            $segment->segmentContents()->orderBy('position')->get(),
        );
    }
}
