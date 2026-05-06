<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;

final class FinishReadingPlanSubscriptionAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

    public function execute(ReadingPlanSubscription $subscription): ReadingPlanSubscription
    {
        if ($subscription->status === SubscriptionStatus::Completed) {
            return $this->withProgressCounts($subscription);
        }

        /** @var array<int, int> $pending */
        $pending = $subscription->days()
            ->whereNull('completed_at')
            ->join(
                'reading_plan_days',
                'reading_plan_days.id',
                '=',
                'reading_plan_subscription_days.reading_plan_day_id',
            )
            ->orderBy('reading_plan_days.position')
            ->pluck('reading_plan_days.position')
            ->all();

        if ($pending !== []) {
            throw new SubscriptionNotCompletableException($pending);
        }

        $subscription->status = SubscriptionStatus::Completed;
        $subscription->completed_at = now();
        $subscription->save();

        $subscription->loadMissing('readingPlan');
        $plan = $subscription->readingPlan;

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::ReadingPlanSubscriptionCompleted,
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
            ],
        );

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
