<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Olympiad\Support\OlympiadCacheKeys;
use App\Http\Resources\Olympiad\OlympiadThemeResource;
use App\Support\Caching\CachedRead;

final class ListOlympiadThemesAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(OlympiadThemeFilter $filter, int $page): array
    {
        return $this->cache->read(
            OlympiadCacheKeys::themesList($filter->language, $page, $filter->perPage),
            OlympiadCacheKeys::tagsForThemesList(),
            3600,
            static function () use ($filter, $page): array {
                $paginator = OlympiadQuestion::query()
                    ->forLanguage($filter->language)
                    ->themes()
                    ->paginate($filter->perPage, page: $page);

                return OlympiadThemeResource::collection($paginator)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
