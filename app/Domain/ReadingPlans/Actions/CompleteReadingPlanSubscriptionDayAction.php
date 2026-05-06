<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use Illuminate\Support\Carbon;

final class CompleteReadingPlanSubscriptionDayAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

    public function execute(ReadingPlanSubscriptionDay $day): ReadingPlanSubscriptionDay
    {
        if ($day->completed_at !== null) {
            return $day;
        }

        $day->completed_at = now();
        $day->save();

        $day->loadMissing(['readingPlanDay', 'subscription.readingPlan']);

        $subscription = $day->subscription;
        $plan = $subscription->readingPlan;
        $position = (int) ($day->readingPlanDay->position ?? 0);
        $start = Carbon::parse($subscription->start_date);
        $ageDays = max(0, (int) $start->diffInDays(now()));

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::ReadingPlanSubscriptionDayCompleted,
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
                'day_position' => $position,
                'subscription_age_days' => $ageDays,
            ],
        );

        return $day;
    }
}
