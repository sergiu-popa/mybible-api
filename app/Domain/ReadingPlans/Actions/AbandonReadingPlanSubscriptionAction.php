<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;

final class AbandonReadingPlanSubscriptionAction
{
    public function execute(ReadingPlanSubscription $subscription): ReadingPlanSubscription
    {
        if ($subscription->status === SubscriptionStatus::Completed) {
            throw new SubscriptionAlreadyCompletedException;
        }

        if ($subscription->status === SubscriptionStatus::Abandoned) {
            return $this->withProgressCounts($subscription);
        }

        $subscription->status = SubscriptionStatus::Abandoned;
        $subscription->save();

        return $this->withProgressCounts($subscription);
    }

    private function withProgressCounts(ReadingPlanSubscription $subscription): ReadingPlanSubscription
    {
        return $subscription->loadCount([
            'days',
            'days as completed_days_count' => fn ($query) => $query->whereNotNull('completed_at'),
        ]);
    }
}
