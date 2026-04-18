<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\InteractsWithReadingPlans;
use Tests\TestCase;

final class StartReadingPlanSubscriptionTest extends TestCase
{
    use InteractsWithAuthentication;
    use InteractsWithReadingPlans;
    use RefreshDatabase;

    public function test_it_creates_a_subscription_with_days_defaulting_to_today(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');

        $plan = $this->givenAPublishedReadingPlanWithDays(3);
        $this->givenAnAuthenticatedUser();

        $response = $this->postJson(
            route('reading-plans.subscriptions.store', ['plan' => $plan->slug]),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
            ->assertJsonPath('data.start_date', '2026-05-01')
            ->assertJsonPath('data.progress.total_days', 3)
            ->assertJsonPath('data.progress.completed_days', 0)
            ->assertJsonCount(3, 'data.days');

        $this->assertSame(3, DB::table('reading_plan_subscription_days')->count());

        Carbon::setTestNow();
    }

    public function test_it_creates_a_subscription_with_explicit_future_start_date(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');

        $plan = $this->givenAPublishedReadingPlanWithDays(3);
        $this->givenAnAuthenticatedUser();

        $response = $this->postJson(
            route('reading-plans.subscriptions.store', ['plan' => $plan->slug]),
            ['start_date' => '2026-06-10'],
        )->assertCreated();

        $response->assertJsonPath('data.start_date', '2026-06-10');

        /** @var list<array{position: int, scheduled_date: string}> $days */
        $days = $response->json('data.days');
        usort($days, fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        $scheduled = array_map(fn (array $day): string => $day['scheduled_date'], $days);

        $this->assertSame(['2026-06-10', '2026-06-11', '2026-06-12'], $scheduled);

        Carbon::setTestNow();
    }

    public function test_it_rejects_a_past_start_date(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');

        $plan = $this->givenAPublishedReadingPlanWithDays(1);
        $this->givenAnAuthenticatedUser();

        $this->postJson(
            route('reading-plans.subscriptions.store', ['plan' => $plan->slug]),
            ['start_date' => '2026-04-30'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        Carbon::setTestNow();
    }

    public function test_it_returns_404_for_a_draft_plan(): void
    {
        $plan = ReadingPlan::factory()->draft()->create(['slug' => 'draft-plan']);
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => $plan->slug]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_an_unknown_slug(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => 'nope']))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        $plan = $this->givenAPublishedReadingPlanWithDays(1);

        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => $plan->slug]))
            ->assertUnauthorized();
    }

    public function test_it_allows_multiple_active_subscriptions_to_the_same_plan(): void
    {
        $plan = $this->givenAPublishedReadingPlanWithDays(2);
        $user = $this->givenAnAuthenticatedUser();

        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => $plan->slug]))->assertCreated();
        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => $plan->slug]))->assertCreated();

        $this->assertSame(
            2,
            ReadingPlanSubscription::query()->where('user_id', $user->id)->count(),
        );
    }
}
