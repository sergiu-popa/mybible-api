<?php

declare(strict_types=1);

namespace App\Domain\Analytics\QueryBuilders;

use App\Domain\Analytics\Enums\EventType;
use App\Domain\Analytics\Models\AnalyticsDailyRollup;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<AnalyticsDailyRollup>
 */
final class AnalyticsDailyRollupQueryBuilder extends Builder
{
    public function between(CarbonInterface $from, CarbonInterface $to): self
    {
        return $this->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    public function ofType(EventType|string $type): self
    {
        return $this->where('event_type', $type instanceof EventType ? $type->value : $type);
    }

    /**
     * @param  array<int, EventType|string>  $types
     */
    public function ofTypes(array $types): self
    {
        $values = array_map(
            static fn (EventType|string $t): string => $t instanceof EventType ? $t->value : $t,
            $types,
        );

        return $this->whereIn('event_type', $values);
    }

    public function forLanguage(string $language): self
    {
        return $this->where('language', $language);
    }
}
