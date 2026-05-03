<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\SabbathSchool\SabbathSchoolTrimesterResource;
use App\Support\Caching\CachedRead;

final class ShowTrimesterAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $trimesterId, Language $language): array
    {
        return $this->cache->read(
            SabbathSchoolCacheKeys::trimester($trimesterId, $language),
            SabbathSchoolCacheKeys::tagsForTrimester($trimesterId),
            3600,
            static function () use ($trimesterId, $language): array {
                $trimester = SabbathSchoolTrimester::query()
                    ->forLanguage($language)
                    ->withLessons()
                    ->findOrFail($trimesterId);

                return SabbathSchoolTrimesterResource::make($trimester)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
