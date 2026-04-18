<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class FinishReadingPlanSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_subscription_completed_when_all_days_are_done(): void
    {
        Carbon::setTestNow('2026-05-10 14:30:00');

        [$user, $subscription] = $this->seedSubscription([
            1 => true,
            2 => true,
            3 => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.finish', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Completed->value)
            ->assertJsonPath('data.completed_at', Carbon::parse('2026-05-10 14:30:00')->toIso8601String())
            ->assertJsonPath('data.progress.total_days', 3)
            ->assertJsonPath('data.progress.completed_days', 3);

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(SubscriptionStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame('2026-05-10 14:30:00', $fresh->completed_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_it_returns_422_with_pending_days_when_days_remain(): void
    {
        [$user, $subscription] = $this->seedSubscription([
            1 => true,
            2 => false,
            3 => true,
            4 => false,
            5 => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.finish', ['subscription' => $subscription->id]))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Subscription cannot be finished while days are pending.',
                'pending_days' => [2, 4, 5],
            ]);

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()?->status);
    }

    public function test_it_is_idempotent_for_already_completed_subscription(): void
    {
        $originalCompletedAt = Carbon::parse('2026-04-01 09:00:00');

        [$user, $subscription] = $this->seedSubscription([1 => true]);
        $subscription->status = SubscriptionStatus::Completed;
        $subscription->completed_at = $originalCompletedAt;
        $subscription->save();

        Carbon::setTestNow('2026-05-10 14:30:00');

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.finish', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Completed->value)
            ->assertJsonPath('data.completed_at', $originalCompletedAt->toIso8601String());

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(
            $originalCompletedAt->toDateTimeString(),
            $fresh->completed_at->toDateTimeString(),
        );

        Carbon::setTestNow();
    }

    public function test_it_returns_403_for_non_owner(): void
    {
        [, $subscription] = $this->seedSubscription([1 => true]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('reading-plan-subscriptions.finish', ['subscription' => $subscription->id]))
            ->assertForbidden();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        [, $subscription] = $this->seedSubscription([1 => true]);

        $this->postJson(route('reading-plan-subscriptions.finish', ['subscription' => $subscription->id]))
            ->assertUnauthorized();
    }

    /**
     * @param  array<int, bool>  $dayCompletion
     * @return array{0: User, 1: ReadingPlanSubscription}
     */
    private function seedSubscription(array $dayCompletion): array
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

        return [$user, $subscription];
    }
}
