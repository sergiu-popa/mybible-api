<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\InteractsWithReadingPlans;
use Tests\TestCase;

final class RescheduleReadingPlanSubscriptionTest extends TestCase
{
    use InteractsWithAuthentication;
    use InteractsWithReadingPlans;
    use RefreshDatabase;

    public function test_it_reanchors_uncompleted_days_and_preserves_completed_ones(): void
    {
        Carbon::setTestNow('2026-05-04 08:00:00');

        [$user, $subscription, $days] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => '2026-05-01 10:00:00'],
            2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
            3 => ['scheduled_date' => '2026-05-03', 'completed_at' => null],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2026-05-10'],
        )
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.start_date', '2026-05-10')
            ->assertJsonPath('data.progress.total_days', 3)
            ->assertJsonPath('data.progress.completed_days', 1);

        $this->assertSame('2026-05-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-10', $days[2]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-11', $days[3]->fresh()?->scheduled_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_it_handles_the_catch_up_walk_through(): void
    {
        Carbon::setTestNow('2026-05-07 08:00:00');

        [$user, $subscription, $days] = $this->givenASubscriptionScheduledAs(
            [
                1 => ['scheduled_date' => '2026-05-04', 'completed_at' => '2026-05-04 08:00:00'],
                2 => ['scheduled_date' => '2026-05-05', 'completed_at' => null],
                3 => ['scheduled_date' => '2026-05-06', 'completed_at' => null],
                4 => ['scheduled_date' => '2026-05-07', 'completed_at' => null],
                5 => ['scheduled_date' => '2026-05-08', 'completed_at' => null],
                6 => ['scheduled_date' => '2026-05-09', 'completed_at' => null],
                7 => ['scheduled_date' => '2026-05-10', 'completed_at' => null],
            ],
            subscriptionStartDate: '2026-05-04',
        );

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2026-05-07'],
        )->assertOk();

        $this->assertSame('2026-05-07', $subscription->fresh()?->start_date->toDateString());
        $this->assertSame('2026-05-04', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-07', $days[2]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-08', $days[3]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-09', $days[4]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-10', $days[5]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-11', $days[6]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-12', $days[7]->fresh()?->scheduled_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_it_updates_start_date_when_position_one_is_completed(): void
    {
        Carbon::setTestNow('2026-05-04 08:00:00');

        [$user, $subscription, $days] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => '2026-05-01 10:00:00'],
            2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2026-06-01'],
        )->assertOk();

        $this->assertSame('2026-06-01', $subscription->fresh()?->start_date->toDateString());
        $this->assertSame('2026-05-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-01', $days[2]->fresh()?->scheduled_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_it_rejects_missing_start_date(): void
    {
        [$user, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_it_rejects_non_date_start_date(): void
    {
        [$user, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => 'not-a-date'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_it_rejects_past_start_date(): void
    {
        Carbon::setTestNow('2026-05-04 08:00:00');

        [$user, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-04', 'completed_at' => null],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2026-05-03'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        Carbon::setTestNow();
    }

    public function test_it_returns_403_when_subscription_belongs_to_another_user(): void
    {
        [, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
        ]);

        $this->givenAnAuthenticatedUser();

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2030-01-01'],
        )->assertForbidden();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        [, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
        ]);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2030-01-01'],
        )->assertUnauthorized();
    }

    public function test_it_returns_404_for_a_soft_deleted_subscription(): void
    {
        [$user, $subscription] = $this->givenASubscriptionScheduledAs([
            1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
        ]);
        $subscription->delete();

        Sanctum::actingAs($user);

        $this->patchJson(
            route('reading-plan-subscriptions.reschedule', ['subscription' => $subscription->id]),
            ['start_date' => '2030-01-01'],
        )->assertNotFound();
    }

    /**
     * @param  array<int, array{scheduled_date: string, completed_at: string|null}>  $dayStates
     * @return array{0: User, 1: ReadingPlanSubscription, 2: array<int, ReadingPlanSubscriptionDay>}
     */
    private function givenASubscriptionScheduledAs(array $dayStates, ?string $subscriptionStartDate = null): array
    {
        $user = User::factory()->create();
        $plan = $this->givenAPublishedReadingPlan();
        $subscription = $this->givenAnActiveSubscriptionTo($plan, $user, [
            'start_date' => $subscriptionStartDate ?? array_values($dayStates)[0]['scheduled_date'],
        ]);

        $days = [];
        foreach ($dayStates as $position => $state) {
            $planDay = ReadingPlanDay::factory()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            $days[$position] = $this->givenASubscriptionDay($subscription, $planDay, [
                'scheduled_date' => $state['scheduled_date'],
                'completed_at' => $state['completed_at'],
            ]);
        }

        return [$user, $subscription, $days];
    }
}
