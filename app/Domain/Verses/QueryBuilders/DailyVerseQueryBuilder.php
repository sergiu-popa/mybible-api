<?php

declare(strict_types=1);

namespace App\Domain\Verses\QueryBuilders;

use App\Domain\Verses\Models\DailyVerse;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<DailyVerse>
 */
final class DailyVerseQueryBuilder extends Builder
{
    public function forDate(DateTimeImmutable $date): ?DailyVerse
    {
        /** @var DailyVerse|null */
        return $this->where('for_date', $date->format('Y-m-d'))->first();
    }
}
