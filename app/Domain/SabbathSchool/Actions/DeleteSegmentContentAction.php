<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteSegmentContentAction
{
    public function execute(SabbathSchoolSegmentContent $content): void
    {
        $lessonId = $content->segment()->value('sabbath_school_lesson_id');

        $content->delete();

        if (is_int($lessonId)) {
            Cache::tags(SabbathSchoolCacheKeys::tagsForLesson($lessonId))->flush();
        }
    }
}
