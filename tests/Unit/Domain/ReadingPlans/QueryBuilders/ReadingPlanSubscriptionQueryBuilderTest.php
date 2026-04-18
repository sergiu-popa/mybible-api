<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\QueryBuilders;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReadingPlanSubscriptionQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_returns_only_that_users_subscriptions(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceSub = ReadingPlanSubscription::factory()->create([
            'user_id' => $alice->id,
            'reading_plan_id' => $plan->id,
        ]);
        ReadingPlanSubscription::factory()->create([
            'user_id' => $bob->id,
            'reading_plan_id' => $plan->id,
        ]);

        $ids = ReadingPlanSubscription::query()->forUser($alice)->pluck('id')->all();

        $this->assertSame([$aliceSub->id], $ids);
    }

    public function test_with_progress_counts_reports_total_and_completed_days(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        $user = User::factory()->create();

        $subscription = ReadingPlanSubscription::factory()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $plan->id,
        ]);

        $days = ReadingPlanDay::factory()
            ->count(3)
            ->state(new Sequence(
                ['position' => 1],
                ['position' => 2],
                ['position' => 3],
            ))
            ->create(['reading_plan_id' => $plan->id]);

        ReadingPlanSubscriptionDay::factory()->completed()->create([
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day_id' => $days[0]->id,
        ]);
        ReadingPlanSubscriptionDay::factory()->pending()->create([
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day_id' => $days[1]->id,
        ]);
        ReadingPlanSubscriptionDay::factory()->completed()->create([
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day_id' => $days[2]->id,
        ]);

        $loaded = ReadingPlanSubscription::query()
            ->withProgressCounts()
            ->find($subscription->id);

        $this->assertInstanceOf(ReadingPlanSubscription::class, $loaded);
        $this->assertSame(3, $loaded->days_count);
        $this->assertSame(2, $loaded->completed_days_count);
    }
}
