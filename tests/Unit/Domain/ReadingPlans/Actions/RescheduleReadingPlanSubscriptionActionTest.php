<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Actions\RescheduleReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\DataTransferObjects\RescheduleReadingPlanSubscriptionData;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RescheduleReadingPlanSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_completed_day_dates_and_reanchors_uncompleted_days(): void
    {
        [$subscription, $days] = $this->seedSubscription(
            startDate: '2026-05-01',
            dayStates: [
                1 => ['scheduled_date' => '2026-05-01', 'completed_at' => '2026-05-01 10:00:00'],
                2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
                3 => ['scheduled_date' => '2026-05-03', 'completed_at' => null],
            ],
        );

        $this->app->make(RescheduleReadingPlanSubscriptionAction::class)
            ->execute(new RescheduleReadingPlanSubscriptionData(
                subscription: $subscription,
                startDate: CarbonImmutable::parse('2026-06-10'),
            ));

        $this->assertSame('2026-06-10', $subscription->fresh()?->start_date->toDateString());

        $this->assertSame('2026-05-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-10', $days[2]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-11', $days[3]->fresh()?->scheduled_date->toDateString());
    }

    public function test_it_reanchors_uncompleted_days_consecutively_from_new_start_date(): void
    {
        [$subscription, $days] = $this->seedSubscription(
            startDate: '2026-05-01',
            dayStates: [
                1 => ['scheduled_date' => '2026-05-01', 'completed_at' => null],
                2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
                3 => ['scheduled_date' => '2026-05-03', 'completed_at' => null],
            ],
        );

        $this->app->make(RescheduleReadingPlanSubscriptionAction::class)
            ->execute(new RescheduleReadingPlanSubscriptionData(
                subscription: $subscription,
                startDate: CarbonImmutable::parse('2026-07-01'),
            ));

        $this->assertSame('2026-07-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-07-02', $days[2]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-07-03', $days[3]->fresh()?->scheduled_date->toDateString());
    }

    public function test_it_updates_start_date_even_when_position_one_is_completed(): void
    {
        [$subscription, $days] = $this->seedSubscription(
            startDate: '2026-05-01',
            dayStates: [
                1 => ['scheduled_date' => '2026-05-01', 'completed_at' => '2026-05-01 08:00:00'],
                2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
            ],
        );

        $this->app->make(RescheduleReadingPlanSubscriptionAction::class)
            ->execute(new RescheduleReadingPlanSubscriptionData(
                subscription: $subscription,
                startDate: CarbonImmutable::parse('2026-06-01'),
            ));

        $this->assertSame('2026-06-01', $subscription->fresh()?->start_date->toDateString());
        $this->assertSame('2026-05-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-01', $days[2]->fresh()?->scheduled_date->toDateString());
    }

    public function test_it_handles_mixed_completed_and_uncompleted_middle_days(): void
    {
        [$subscription, $days] = $this->seedSubscription(
            startDate: '2026-05-01',
            dayStates: [
                1 => ['scheduled_date' => '2026-05-01', 'completed_at' => '2026-05-01 08:00:00'],
                2 => ['scheduled_date' => '2026-05-02', 'completed_at' => null],
                3 => ['scheduled_date' => '2026-05-03', 'completed_at' => '2026-05-03 08:00:00'],
                4 => ['scheduled_date' => '2026-05-04', 'completed_at' => null],
                5 => ['scheduled_date' => '2026-05-05', 'completed_at' => null],
            ],
        );

        $this->app->make(RescheduleReadingPlanSubscriptionAction::class)
            ->execute(new RescheduleReadingPlanSubscriptionData(
                subscription: $subscription,
                startDate: CarbonImmutable::parse('2026-06-10'),
            ));

        $this->assertSame('2026-05-01', $days[1]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-10', $days[2]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-05-03', $days[3]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-11', $days[4]->fresh()?->scheduled_date->toDateString());
        $this->assertSame('2026-06-12', $days[5]->fresh()?->scheduled_date->toDateString());
    }

    /**
     * @param  array<int, array{scheduled_date: string, completed_at: string|null}>  $dayStates
     * @return array{0: ReadingPlanSubscription, 1: array<int, ReadingPlanSubscriptionDay>}
     */
    private function seedSubscription(string $startDate, array $dayStates): array
    {
        $plan = ReadingPlan::factory()->published()->create();
        $user = User::factory()->create();

        $subscription = ReadingPlanSubscription::factory()->active()->create([
            'user_id' => $user->id,
            'reading_plan_id' => $plan->id,
            'start_date' => $startDate,
        ]);

        $days = [];
        foreach ($dayStates as $position => $state) {
            $planDay = ReadingPlanDay::factory()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            $days[$position] = ReadingPlanSubscriptionDay::factory()->create([
                'reading_plan_subscription_id' => $subscription->id,
                'reading_plan_day_id' => $planDay->id,
                'scheduled_date' => $state['scheduled_date'],
                'completed_at' => $state['completed_at'],
            ]);
        }

        return [$subscription, $days];
    }
}
