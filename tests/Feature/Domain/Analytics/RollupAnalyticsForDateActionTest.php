<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Analytics;

use App\Domain\Analytics\Actions\RollupAnalyticsForDateAction;
use App\Domain\Analytics\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RollupAnalyticsForDateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_events_into_rollups(): void
    {
        $date = CarbonImmutable::parse('2026-04-15');

        // 100 events for one event type, 5 distinct users, mixed devices.
        for ($i = 0; $i < 100; $i++) {
            AnalyticsEvent::factory()->create([
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => 1,
                'language' => 'ro',
                'user_id' => ($i % 5) + 1,
                'device_id' => 'd' . (($i % 7) + 1),
                'occurred_at' => $date->setTime(12, $i % 60),
            ]);
        }

        app(RollupAnalyticsForDateAction::class)->execute($date);

        $row = DB::table('analytics_daily_rollups')
            ->where('date', $date->toDateString())
            ->where('event_type', 'devotional.viewed')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(100, (int) $row->event_count);
        $this->assertSame(5, (int) $row->unique_users);
        $this->assertSame(7, (int) $row->unique_devices);

        $this->assertSame(
            5,
            DB::table('analytics_user_active_daily')
                ->where('date', $date->toDateString())
                ->count(),
        );
        $this->assertSame(
            7,
            DB::table('analytics_device_active_daily')
                ->where('date', $date->toDateString())
                ->count(),
        );
    }

    public function test_rerun_is_idempotent(): void
    {
        $date = CarbonImmutable::parse('2026-04-16');

        AnalyticsEvent::factory()->count(10)->create([
            'event_type' => 'devotional.viewed',
            'subject_type' => 'devotional',
            'subject_id' => 2,
            'language' => 'ro',
            'occurred_at' => $date->setTime(10, 0),
        ]);

        $action = app(RollupAnalyticsForDateAction::class);
        $action->execute($date);
        $action->execute($date);

        $this->assertSame(
            1,
            DB::table('analytics_daily_rollups')
                ->where('date', $date->toDateString())
                ->where('event_type', 'devotional.viewed')
                ->count(),
        );
    }
}
