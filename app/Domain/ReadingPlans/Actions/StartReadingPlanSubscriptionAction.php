<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\DataTransferObjects\StartReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Support\Facades\DB;

final class StartReadingPlanSubscriptionAction
{
    public function execute(StartReadingPlanSubscriptionData $data): ReadingPlanSubscription
    {
        return DB::transaction(function () use ($data): ReadingPlanSubscription {
            $subscription = ReadingPlanSubscription::query()->create([
                'user_id' => $data->user->id,
                'reading_plan_id' => $data->plan->id,
                'start_date' => $data->startDate->toDateString(),
                'status' => SubscriptionStatus::Active,
            ]);

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
    }
}
