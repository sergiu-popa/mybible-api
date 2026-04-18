<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CompleteReadingPlanSubscriptionDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_day_as_completed(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        [$user, $subscription, $day] = $this->seedSubscriptionAndDay();

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.id', $day->id)
            ->assertJsonPath('data.completed_at', Carbon::parse('2026-05-01 10:00:00')->toIso8601String());

        $refreshed = $day->fresh();
        $this->assertNotNull($refreshed);
        $this->assertNotNull($refreshed->completed_at);

        Carbon::setTestNow();
    }

    public function test_it_is_idempotent_and_preserves_original_completed_at(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        [$user, $subscription, $day] = $this->seedSubscriptionAndDay();

        $day->forceFill(['completed_at' => '2026-04-15 09:00:00'])->save();

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.completed_at', Carbon::parse('2026-04-15 09:00:00')->toIso8601String());

        $refreshed = $day->fresh();
        $this->assertNotNull($refreshed);
        $this->assertNotNull($refreshed->completed_at);
        $this->assertSame(
            '2026-04-15 09:00:00',
            $refreshed->completed_at->toDateTimeString(),
        );

        Carbon::setTestNow();
    }

    public function test_it_returns_403_when_subscription_belongs_to_another_user(): void
    {
        [, $subscription, $day] = $this->seedSubscriptionAndDay();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))->assertForbidden();
    }

    public function test_it_returns_404_when_the_day_belongs_to_another_subscription(): void
    {
        [$user, $subscription] = $this->seedSubscriptionAndDay();

        $otherPlan = ReadingPlan::factory()->published()->create();
        $otherPlanDay = ReadingPlanDay::factory()->create(['reading_plan_id' => $otherPlan->id, 'position' => 1]);
        $otherSubscription = ReadingPlanSubscription::factory()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $otherPlan->id,
        ]);
        $otherDay = ReadingPlanSubscriptionDay::factory()->pending()->create([
            'reading_plan_subscription_id' => $otherSubscription->id,
            'reading_plan_day_id' => $otherPlanDay->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $otherDay->id,
        ]))->assertNotFound();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        [, $subscription, $day] = $this->seedSubscriptionAndDay();

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: ReadingPlanSubscription, 2: ReadingPlanSubscriptionDay}
     */
    private function seedSubscriptionAndDay(): array
    {
        $plan = ReadingPlan::factory()->published()->create();
        $planDay = ReadingPlanDay::factory()->create(['reading_plan_id' => $plan->id, 'position' => 1]);

        $user = User::factory()->create();
        $subscription = ReadingPlanSubscription::factory()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $plan->id,
        ]);
        $day = ReadingPlanSubscriptionDay::factory()->pending()->create([
            'reading_plan_subscription_id' => $subscription->id,
            'reading_plan_day_id' => $planDay->id,
        ]);

        return [$user, $subscription, $day];
    }
}
