<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\LessonData;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class CreateLessonAction
{
    public function execute(LessonData $data): SabbathSchoolLesson
    {
        $lesson = SabbathSchoolLesson::create($data->toArray());

        Cache::tags(SabbathSchoolCacheKeys::tagsForLessonsList())->flush();

        return $lesson;
    }
}
