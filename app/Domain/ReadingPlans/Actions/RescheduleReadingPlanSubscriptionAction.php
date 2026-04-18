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

            foreach ($uncompleted as $index => $day) {
                $day->scheduled_date = Carbon::instance($data->startDate->addDays($index));
                $day->save();
            }

            return $subscription->loadCount([
                'days',
                'days as completed_days_count' => fn ($query) => $query->whereNotNull('completed_at'),
            ]);
        });
    }
}
