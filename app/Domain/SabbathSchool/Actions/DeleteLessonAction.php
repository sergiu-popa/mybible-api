<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteLessonAction
{
    public function execute(SabbathSchoolLesson $lesson): void
    {
        $id = $lesson->id;
        $lesson->delete();

        Cache::tags(SabbathSchoolCacheKeys::tagsForLesson($id))->flush();
        Cache::tags(SabbathSchoolCacheKeys::tagsForLessonsList())->flush();
    }
}
