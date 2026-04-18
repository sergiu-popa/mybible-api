<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\QueryBuilders;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ReadingPlanSubscription>
 */
final class ReadingPlanSubscriptionQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function withProgressCounts(): self
    {
        return $this->withCount([
            'days',
            'days as completed_days_count' => fn (Builder $query): Builder => $query->whereNotNull('completed_at'),
        ]);
    }

    public function withDaysOrdered(): self
    {
        return $this->with([
            'days' => fn ($query) => $query
                ->join('reading_plan_days', 'reading_plan_days.id', '=', 'reading_plan_subscription_days.reading_plan_day_id')
                ->orderBy('reading_plan_days.position')
                ->select('reading_plan_subscription_days.*')
                ->with('readingPlanDay'),
        ]);
    }
}
