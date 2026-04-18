<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\QueryBuilders;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ReadingPlan>
 */
final class ReadingPlanQueryBuilder extends Builder
{
    public function published(): self
    {
        return $this
            ->where('status', ReadingPlanStatus::Published->value)
            ->whereNotNull('published_at');
    }

    public function withDaysAndFragments(): self
    {
        return $this->with(['days.fragments']);
    }
}
