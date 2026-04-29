<?php

declare(strict_types=1);

namespace App\Domain\Bible\Actions;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\Bible\BibleVersionResource;
use App\Support\Caching\CachedRead;

final class ListBibleVersionsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(?Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            BibleCacheKeys::versionsList($language, $page, $perPage),
            BibleCacheKeys::tagsForVersionList(),
            86400,
            static function () use ($language, $page, $perPage): array {
                $query = BibleVersion::query()->orderBy('abbreviation');

                if ($language !== null) {
                    $query->forLanguage($language);
                }

                $paginator = $query->paginate($perPage, page: $page);

                return BibleVersionResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
