<?php

declare(strict_types=1);

namespace App\Domain\Analytics\QueryBuilders;

use App\Domain\Analytics\Enums\EventType;
use App\Domain\Analytics\Models\AnalyticsEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<AnalyticsEvent>
 */
final class AnalyticsEventQueryBuilder extends Builder
{
    public function inWindow(CarbonInterface $from, CarbonInterface $to): self
    {
        return $this->whereBetween('occurred_at', [$from, $to]);
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

    public function forSubject(string $subjectType, int $subjectId): self
    {
        return $this
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);
    }

    public function forUser(int $userId): self
    {
        return $this->where('user_id', $userId);
    }

    public function forDevice(string $deviceId): self
    {
        return $this->where('device_id', $deviceId);
    }
}
