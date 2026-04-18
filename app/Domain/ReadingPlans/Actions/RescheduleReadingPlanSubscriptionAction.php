<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\DataTransferObjects\RescheduleReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RescheduleReadingPlanSubscriptionAction
{
    public function execute(RescheduleReadingPlanSubscriptionData $data): ReadingPlanSubscription
    {
        return DB::transaction(function () use ($data): ReadingPlanSubscription {
            $subscription = $data->subscription;
            $subscription->start_date = Carbon::instance($data->startDate);
            $subscription->save();

            $uncompleted = $subscription->days()
                ->whereNull('completed_at')
                ->with('readingPlanDay')
                ->get()
                ->sortBy(fn ($day): int => $day->readingPlanDay->position)
                ->values();

            if ($uncompleted->isNotEmpty()) {
                $now = now();
                $rows = [];
                foreach ($uncompleted as $index => $day) {
                    $rows[] = [
                        'reading_plan_subscription_id' => $day->reading_plan_subscription_id,
                        'reading_plan_day_id' => $day->reading_plan_day_id,
                        'scheduled_date' => $data->startDate->addDays($index)->toDateString(),
                        'updated_at' => $now,
                        'created_at' => $day->created_at ?? $now,
                    ];
                }

                // Single round-trip via upsert on the (subscription_id, day_id)
                // unique index — INSERT path is never taken for existing rows,
                // but all NOT NULL columns must be supplied for MySQL to parse
                // the statement.
                DB::table('reading_plan_subscription_days')->upsert(
                    $rows,
                    ['reading_plan_subscription_id', 'reading_plan_day_id'],
                    ['scheduled_date', 'updated_at'],
                );
            }

            return $subscription->loadCount([
                'days',
                'days as completed_days_count' => fn ($query) => $query->whereNotNull('completed_at'),
            ]);
        });
    }
}
