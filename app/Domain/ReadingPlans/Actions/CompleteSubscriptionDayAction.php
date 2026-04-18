<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;

final class CompleteSubscriptionDayAction
{
    public function execute(ReadingPlanSubscriptionDay $day): ReadingPlanSubscriptionDay
    {
        if ($day->completed_at !== null) {
            return $day;
        }

        $day->completed_at = now();
        $day->save();

        return $day;
    }
}
