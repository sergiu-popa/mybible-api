<?php

declare(strict_types=1);

namespace App\Domain\News\Actions;

use App\Domain\News\Models\News;
use App\Domain\News\Support\NewsCacheKeys;
use App\Http\Resources\News\NewsDetailResource;
use App\Support\Caching\CachedRead;

final class ShowNewsAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(News $news): array
    {
        return $this->cache->read(
            NewsCacheKeys::show($news->id),
            NewsCacheKeys::tagsForNews(),
            300,
            static function () use ($news): array {
                return NewsDetailResource::make($news)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
