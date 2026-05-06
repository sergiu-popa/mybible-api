<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\ReadingPlans\DataTransferObjects\StartReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Support\Facades\DB;

final class StartReadingPlanSubscriptionAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

    /**
     * Multiple active subscriptions to the same plan for the same user are
     * intentionally allowed (e.g., a user restarting a plan alongside the
     * original run). Pinned by
     * StartReadingPlanSubscriptionTest::test_it_allows_multiple_active_subscriptions_to_the_same_plan.
     */
    public function execute(StartReadingPlanSubscriptionData $data): ReadingPlanSubscription
    {
        $subscription = DB::transaction(function () use ($data): ReadingPlanSubscription {
            $subscription = ReadingPlanSubscription::query()->create([
                'user_id' => $data->user->id,
                'reading_plan_id' => $data->plan->id,
                'start_date' => $data->startDate->toDateString(),
                'status' => SubscriptionStatus::Active,
            ]);

            $data->plan->loadMissing('days');

            $now = now();
            $rows = [];
            foreach ($data->plan->days as $planDay) {
                $rows[] = [
                    'reading_plan_subscription_id' => $subscription->id,
                    'reading_plan_day_id' => $planDay->id,
                    'scheduled_date' => $data->startDate->addDays($planDay->position - 1)->toDateString(),
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Raw insert (over Eloquent mass-create) to materialize all day rows
            // in a single round-trip. Trade-off: bypasses model events; any
            // future ReadingPlanSubscriptionDay observer must be invoked here
            // explicitly.
            if ($rows !== []) {
                DB::table('reading_plan_subscription_days')->insert($rows);
            }

            return $subscription
                ->load(['days.readingPlanDay'])
                ->loadCount([
                    'days',
                    'days as completed_days_count' => fn ($query) => $query->whereNotNull('completed_at'),
                ]);
        });

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::ReadingPlanSubscriptionStarted,
            context: new ResourceDownloadContextData(
                userId: (int) $subscription->user_id,
                deviceId: null,
                language: null,
                source: null,
            ),
            subject: $subscription,
            metadata: [
                'plan_id' => (int) $data->plan->id,
                'plan_slug' => (string) $data->plan->slug,
            ],
        );

        return $subscription;
    }
}
