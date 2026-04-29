<?php

declare(strict_types=1);

namespace App\Domain\Verses\Actions;

use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Domain\Verses\Models\DailyVerse;
use App\Domain\Verses\Support\VersesCacheKeys;
use App\Http\Resources\Verses\DailyVerseResource;
use App\Support\Caching\CachedRead;
use DateTimeImmutable;

final class GetDailyVerseAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(DateTimeImmutable $date): array
    {
        return $this->cache->read(
            VersesCacheKeys::dailyVerse($date),
            VersesCacheKeys::tagsForDailyVerse(),
            1800,
            static function () use ($date): array {
                $verse = DailyVerse::query()->forDate($date);

                if ($verse === null) {
                    throw new NoDailyVerseForDateException($date);
                }

                return DailyVerseResource::make($verse)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
