<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\SabbathSchool\SabbathSchoolTrimesterResource;
use App\Support\Caching\CachedRead;

final class ListTrimestersAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Language $language): array
    {
        return $this->cache->read(
            SabbathSchoolCacheKeys::trimestersList($language),
            SabbathSchoolCacheKeys::tagsForTrimestersList(),
            3600,
            static function () use ($language): array {
                $trimesters = SabbathSchoolTrimester::query()
                    ->forLanguage($language)
                    ->orderByDesc('year')
                    ->orderByDesc('number')
                    ->get();

                return SabbathSchoolTrimesterResource::collection($trimesters)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
