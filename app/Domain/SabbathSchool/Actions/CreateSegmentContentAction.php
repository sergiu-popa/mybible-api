<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\SegmentContentData;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class CreateSegmentContentAction
{
    public function execute(SabbathSchoolSegment $segment, SegmentContentData $data): SabbathSchoolSegmentContent
    {
        $content = SabbathSchoolSegmentContent::create([
            'segment_id' => $segment->id,
            ...$data->toArray(),
        ]);

        Cache::tags(SabbathSchoolCacheKeys::tagsForLesson($segment->sabbath_school_lesson_id))->flush();

        return $content;
    }
}
