<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ReadingPlanSubscriptionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_uses_withcount_aggregates_when_available(): void
    {
        $subscription = $this->freshSubscription();
        $subscription->setAttribute('days_count', 3);
        $subscription->setAttribute('completed_days_count', 2);

        $payload = (new ReadingPlanSubscriptionResource($subscription))->toArray(Request::create('/'));

        $this->assertSame(['completed_days' => 2, 'total_days' => 3], $payload['progress']);
    }

    public function test_progress_falls_back_to_collection_counts_when_loaded(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        ReadingPlanDay::factory()
            ->count(3)
            ->state(new Sequence(['position' => 1], ['position' => 2], ['position' => 3]))
            ->create(['reading_plan_id' => $plan->id]);

        $subscription = ReadingPlanSubscription::factory()->create(['reading_plan_id' => $plan->id]);

        foreach ($plan->days as $index => $day) {
            ReadingPlanSubscriptionDay::factory()
                ->{$index === 0 ? 'completed' : 'pending'}()
                ->create([
                    'reading_plan_subscription_id' => $subscription->id,
                    'reading_plan_day_id' => $day->id,
                ]);
        }

        $subscription->load('days');

        $payload = (new ReadingPlanSubscriptionResource($subscription))->toArray(Request::create('/'));

        $this->assertSame(['completed_days' => 1, 'total_days' => 3], $payload['progress']);
    }

    private function freshSubscription(): ReadingPlanSubscription
    {
        return ReadingPlanSubscription::factory()->make([
            'id' => 1,
            'reading_plan_id' => 7,
            'start_date' => '2026-05-01',
        ]);
    }
}
