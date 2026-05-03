<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateSegmentContentData;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateSegmentContentAction
{
    public function execute(SabbathSchoolSegmentContent $content, UpdateSegmentContentData $data): SabbathSchoolSegmentContent
    {
        $content->fill($data->toArray())->save();

        $lessonId = $content->segment()->value('sabbath_school_lesson_id');
        if (is_int($lessonId)) {
            Cache::tags(SabbathSchoolCacheKeys::tagsForLesson($lessonId))->flush();
        }

        return $content;
    }
}
