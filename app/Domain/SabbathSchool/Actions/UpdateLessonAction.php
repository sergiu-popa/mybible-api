<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateLessonData;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateLessonAction
{
    public function execute(SabbathSchoolLesson $lesson, UpdateLessonData $data): SabbathSchoolLesson
    {
        $lesson->fill($data->toArray())->save();

        Cache::tags(SabbathSchoolCacheKeys::tagsForLesson($lesson->id))->flush();
        Cache::tags(SabbathSchoolCacheKeys::tagsForLessonsList())->flush();

        return $lesson;
    }
}
