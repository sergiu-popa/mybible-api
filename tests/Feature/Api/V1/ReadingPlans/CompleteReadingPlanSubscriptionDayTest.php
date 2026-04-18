<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\InteractsWithReadingPlans;
use Tests\TestCase;

final class CompleteReadingPlanSubscriptionDayTest extends TestCase
{
    use InteractsWithAuthentication;
    use InteractsWithReadingPlans;
    use RefreshDatabase;

    public function test_it_marks_the_day_as_completed(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        [$user, $subscription, $day] = $this->givenASubscriptionWithAPendingDay();

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

        [$user, $subscription, $day] = $this->givenASubscriptionWithAPendingDay();

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
        [, $subscription, $day] = $this->givenASubscriptionWithAPendingDay();

        $this->givenAnAuthenticatedUser();

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))->assertForbidden();
    }

    public function test_it_returns_404_when_the_day_belongs_to_another_subscription(): void
    {
        [$user, $subscription] = $this->givenASubscriptionWithAPendingDay();

        $otherPlan = $this->givenAPublishedReadingPlanWithDays(1);
        $otherDayOfPlan = $otherPlan->days()->firstOrFail();
        $otherSubscription = $this->givenAnActiveSubscriptionTo($otherPlan, $user);
        $otherDay = $this->givenASubscriptionDay($otherSubscription, $otherDayOfPlan, [
            'completed_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $otherDay->id,
        ]))->assertNotFound();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        [, $subscription, $day] = $this->givenASubscriptionWithAPendingDay();

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: ReadingPlanSubscription, 2: ReadingPlanSubscriptionDay}
     */
    private function givenASubscriptionWithAPendingDay(): array
    {
        $plan = $this->givenAPublishedReadingPlanWithDays(1);
        $planDay = $plan->days()->firstOrFail();

        $user = User::factory()->create();
        $subscription = $this->givenAnActiveSubscriptionTo($plan, $user);
        $day = $this->givenASubscriptionDay($subscription, $planDay, [
            'completed_at' => null,
        ]);

        return [$user, $subscription, $day];
    }
}
