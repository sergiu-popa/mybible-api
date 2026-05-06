<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;

final class AbandonReadingPlanSubscriptionAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

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

        $subscription->loadMissing('readingPlan');
        $plan = $subscription->readingPlan;

        $withCounts = $this->withProgressCounts($subscription);

        $totalDays = (int) ($withCounts->getAttribute('days_count') ?? 0);
        $completed = (int) ($withCounts->getAttribute('completed_days_count') ?? 0);
        // The "at_day" is the next un-completed day, i.e. completed + 1.
        $atDay = max(1, $completed + 1);

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::ReadingPlanSubscriptionAbandoned,
            context: new ResourceDownloadContextData(
                userId: (int) $subscription->user_id,
                deviceId: null,
                language: null,
                source: null,
            ),
            subject: $subscription,
            metadata: [
                'plan_id' => (int) $plan->id,
                'plan_slug' => (string) $plan->slug,
                'at_day_position' => $atDay,
                'total_days' => max(1, $totalDays),
            ],
        );

        return $withCounts;
    }

    private function withProgressCounts(ReadingPlanSubscription $subscription): ReadingPlanSubscription
    {
        return $subscription->loadCount([
            'days',
            'days as completed_days_count' => fn ($query) => $query->whereNotNull('completed_at'),
        ]);
    }
}
