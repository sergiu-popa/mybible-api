<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonResource;
use App\Support\Caching\CachedRead;

final class ShowSabbathSchoolLessonAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $lessonId, Language $language): array
    {
        return $this->cache->read(
            SabbathSchoolCacheKeys::lesson($lessonId, $language),
            SabbathSchoolCacheKeys::tagsForLesson($lessonId),
            3600,
            static function () use ($lessonId): array {
                // Implicit route-model binding skipped on purpose: with the
                // bind running before the Action, the published()+detail
                // query would fire on every cache hit and defeat the cache.
                // The 404 path below stays JSON via the standard exception
                // handler (ModelNotFoundException → 404 envelope).
                $lesson = SabbathSchoolLesson::query()
                    ->published()
                    ->withLessonDetail()
                    ->findOrFail($lessonId);

                return SabbathSchoolLessonResource::make($lesson)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
