<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Actions\CompleteSubscriptionDayAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class CompleteSubscriptionDayActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_completed_at_when_pending(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $day = ReadingPlanSubscriptionDay::factory()->pending()->create();

        $result = $this->app->make(CompleteSubscriptionDayAction::class)->execute($day);

        $this->assertNotNull($result->completed_at);
        $this->assertSame('2026-05-01 12:00:00', $result->completed_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_it_preserves_the_original_completed_at_on_repeated_calls(): void
    {
        $originalCompletedAt = CarbonImmutable::parse('2026-04-01 09:00:00');

        $day = ReadingPlanSubscriptionDay::factory()->create([
            'completed_at' => $originalCompletedAt,
        ]);

        Carbon::setTestNow('2026-05-01 12:00:00');

        $action = $this->app->make(CompleteSubscriptionDayAction::class);
        $result = $action->execute($day);
        $result = $action->execute($result->refresh());

        $this->assertNotNull($result->completed_at);
        $this->assertSame(
            $originalCompletedAt->toDateTimeString(),
            $result->completed_at->toDateTimeString(),
        );

        Carbon::setTestNow();
    }
}
