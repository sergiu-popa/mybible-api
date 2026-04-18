<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Actions\FinishReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class FinishReadingPlanSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_throws_with_pending_positions_when_days_remain(): void
    {
        $subscription = $this->seedSubscription([
            1 => true,
            2 => false,
            3 => true,
            4 => false,
            5 => false,
        ]);

        try {
            $this->app->make(FinishReadingPlanSubscriptionAction::class)->execute($subscription);
            $this->fail('Expected SubscriptionNotCompletableException was not thrown.');
        } catch (SubscriptionNotCompletableException $e) {
            $this->assertSame([2, 4, 5], $e->pendingPositions);
        }

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()?->status);
    }

    public function test_it_marks_subscription_completed_when_all_days_are_done(): void
    {
        Carbon::setTestNow('2026-05-10 14:30:00');

        $subscription = $this->seedSubscription([
            1 => true,
            2 => true,
            3 => true,
        ]);

        $result = $this->app->make(FinishReadingPlanSubscriptionAction::class)->execute($subscription);

        $this->assertSame(SubscriptionStatus::Completed, $result->status);
        $this->assertNotNull($result->completed_at);
        $this->assertSame('2026-05-10 14:30:00', $result->completed_at->toDateTimeString());

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(SubscriptionStatus::Completed, $fresh->status);

        Carbon::setTestNow();
    }

    public function test_it_is_idempotent_for_already_completed_subscription(): void
    {
        $original = Carbon::parse('2026-04-01 09:00:00');

        $subscription = $this->seedSubscription([1 => true]);
        $subscription->status = SubscriptionStatus::Completed;
        $subscription->completed_at = $original;
        $subscription->save();

        Carbon::setTestNow('2026-05-10 14:30:00');

        $result = $this->app->make(FinishReadingPlanSubscriptionAction::class)->execute($subscription);

        $this->assertSame(SubscriptionStatus::Completed, $result->status);
        $this->assertNotNull($result->completed_at);
        $this->assertSame('2026-04-01 09:00:00', $result->completed_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * @param  array<int, bool>  $dayCompletion  position => completed?
     */
    private function seedSubscription(array $dayCompletion): ReadingPlanSubscription
    {
        $plan = ReadingPlan::factory()->published()->create();
        $user = User::factory()->create();

        $subscription = ReadingPlanSubscription::factory()->active()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $plan->id,
        ]);

        foreach ($dayCompletion as $position => $completed) {
            $planDay = ReadingPlanDay::factory()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            ReadingPlanSubscriptionDay::factory()->create([
                'reading_plan_subscription_id' => $subscription->id,
                'reading_plan_day_id' => $planDay->id,
                'completed_at' => $completed ? Carbon::parse('2026-04-15 10:00:00') : null,
            ]);
        }

        return $subscription;
    }
}
