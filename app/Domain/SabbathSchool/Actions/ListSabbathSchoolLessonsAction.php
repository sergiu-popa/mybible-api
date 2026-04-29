<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonSummaryResource;
use App\Support\Caching\CachedRead;

final class ListSabbathSchoolLessonsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            SabbathSchoolCacheKeys::lessonsList($language, $page, $perPage),
            SabbathSchoolCacheKeys::tagsForLessonsList(),
            3600,
            static function () use ($language, $perPage, $page): array {
                $paginator = SabbathSchoolLesson::query()
                    ->published()
                    ->forLanguage($language)
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->paginate($perPage, page: $page);

                return SabbathSchoolLessonSummaryResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
