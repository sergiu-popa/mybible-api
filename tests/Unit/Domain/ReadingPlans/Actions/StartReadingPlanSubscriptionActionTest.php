<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Actions\StartReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\DataTransferObjects\StartReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class StartReadingPlanSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_subscription_with_one_day_per_plan_day(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        ReadingPlanDay::factory()
            ->count(3)
            ->state(new Sequence(
                ['position' => 1],
                ['position' => 2],
                ['position' => 3],
            ))
            ->create(['reading_plan_id' => $plan->id]);

        $user = User::factory()->create();
        $startDate = CarbonImmutable::parse('2026-05-01');

        $action = $this->app->make(StartReadingPlanSubscriptionAction::class);
        $plan->load('days');

        $subscription = $action->execute(new StartReadingPlanSubscriptionData(
            user: $user,
            plan: $plan,
            startDate: $startDate,
        ));

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($plan->id, $subscription->reading_plan_id);
        $this->assertSame('2026-05-01', $subscription->start_date->toDateString());

        $subscription->load(['days.readingPlanDay' => fn ($q) => $q->orderBy('position')]);
        $this->assertCount(3, $subscription->days);

        $dates = $subscription->days
            ->sortBy(fn ($d) => $d->readingPlanDay->position)
            ->map(fn ($d): string => $d->scheduled_date->toDateString())
            ->values()
            ->all();

        $this->assertSame(['2026-05-01', '2026-05-02', '2026-05-03'], $dates);
    }

    public function test_it_rolls_back_when_the_day_insert_fails(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        ReadingPlanDay::factory()->create(['reading_plan_id' => $plan->id, 'position' => 1]);

        $user = User::factory()->create();
        $plan->load('days');

        $action = $this->app->make(StartReadingPlanSubscriptionAction::class);

        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into `reading_plan_subscription_days`')) {
                throw new RuntimeException('boom');
            }
        });

        try {
            $action->execute(new StartReadingPlanSubscriptionData(
                user: $user,
                plan: $plan,
                startDate: CarbonImmutable::parse('2026-05-01'),
            ));
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, ReadingPlanSubscription::query()->count());
        $this->assertSame(0, DB::table('reading_plan_subscription_days')->count());
    }
}
