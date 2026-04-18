<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

trait InteractsWithReadingPlans
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function givenAPublishedReadingPlan(array $attributes = []): ReadingPlan
    {
        return ReadingPlan::factory()->published()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function givenAPublishedReadingPlanWithDays(int $dayCount = 1, array $attributes = []): ReadingPlan
    {
        $plan = $this->givenAPublishedReadingPlan($attributes);

        if ($dayCount < 1) {
            return $plan;
        }

        $positions = collect(range(1, $dayCount))
            ->map(fn (int $position): array => ['position' => $position])
            ->all();

        ReadingPlanDay::factory()
            ->count($dayCount)
            ->state(new Sequence(...$positions))
            ->create(['reading_plan_id' => $plan->id]);

        return $plan->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function givenAnActiveSubscriptionTo(ReadingPlan $plan, User $user, array $attributes = []): ReadingPlanSubscription
    {
        return ReadingPlanSubscription::factory()->active()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $plan->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function givenASubscriptionDay(
        ReadingPlanSubscription $subscription,
        ReadingPlanDay $planDay,
        array $attributes = [],
    ): ReadingPlanSubscriptionDay {
        return ReadingPlanSubscriptionDay::factory()->create([
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day_id' => $planDay->id,
            ...$attributes,
        ]);
    }
}
