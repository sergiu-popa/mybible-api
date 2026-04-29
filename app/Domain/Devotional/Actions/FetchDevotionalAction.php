<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Support\DevotionalCacheKeys;
use App\Http\Resources\Devotionals\DevotionalResource;
use App\Support\Caching\CachedRead;

final class FetchDevotionalAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(FetchDevotionalData $data): array
    {
        return $this->cache->read(
            DevotionalCacheKeys::show($data->language, $data->type, $data->date),
            DevotionalCacheKeys::tagsForDevotional($data->language, $data->type),
            3600,
            static function () use ($data): array {
                $devotional = Devotional::query()
                    ->forLanguage($data->language)
                    ->ofType($data->type)
                    ->onDate($data->date)
                    ->firstOrFail();

                return DevotionalResource::make($devotional)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
