<?php

declare(strict_types=1);

namespace App\Domain\News\Actions;

use App\Domain\News\Models\News;
use App\Domain\News\Support\NewsCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\News\NewsResource;
use App\Support\Caching\CachedRead;

final class ListNewsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Language $language, int $page, int $perPage): array
    {
        return $this->cache->read(
            NewsCacheKeys::list($language, $page, $perPage),
            NewsCacheKeys::tagsForNews(),
            600,
            static function () use ($language, $page, $perPage): array {
                $paginator = News::query()
                    ->published()
                    ->forLanguage($language)
                    ->newestFirst()
                    ->paginate($perPage, page: $page);

                return NewsResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
