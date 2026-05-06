<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Analytics;

use App\Domain\Analytics\Models\AnalyticsDailyRollup;
use App\Domain\Analytics\Models\AnalyticsUserActiveDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAnalyticsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_requires_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.summary', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'period' => 'day',
            ]))
            ->assertStatus(403);
    }

    public function test_summary_returns_kpi_panel(): void
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('t')->plainTextToken;

        AnalyticsDailyRollup::factory()->create([
            'date' => '2026-04-15',
            'event_type' => 'devotional.viewed',
            'event_count' => 50,
            'unique_users' => 10,
            'unique_devices' => 12,
        ]);
        AnalyticsDailyRollup::factory()->create([
            'date' => '2026-04-16',
            'event_type' => 'bible.chapter.viewed',
            'event_count' => 200,
            'unique_users' => 30,
            'unique_devices' => 40,
        ]);

        AnalyticsUserActiveDaily::factory()->create(['date' => '2026-04-30', 'user_id' => 1]);
        AnalyticsUserActiveDaily::factory()->create(['date' => '2026-04-30', 'user_id' => 2]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.summary', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['total_events', 'dau', 'mau', 'top_event_types'],
                'meta' => ['from', 'to', 'period'],
            ])
            ->assertJsonPath('data.total_events', 250)
            ->assertJsonPath('data.dau', 2);
    }

    public function test_event_counts_returns_per_day_series(): void
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('t')->plainTextToken;

        AnalyticsDailyRollup::factory()->create([
            'date' => '2026-04-10',
            'event_type' => 'devotional.viewed',
            'event_count' => 10,
        ]);
        AnalyticsDailyRollup::factory()->create([
            'date' => '2026-04-11',
            'event_type' => 'devotional.viewed',
            'event_count' => 20,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.event-counts', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'period' => 'day',
                'event_type' => 'devotional.viewed',
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['date', 'count']],
                'meta' => ['from', 'to', 'period', 'event_type', 'group_by'],
            ]);
    }

    public function test_dau_mau_returns_series(): void
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.dau-mau', [
                'from' => '2026-04-29',
                'to' => '2026-04-30',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['date', 'dau_users', 'mau_users', 'dau_devices', 'mau_devices']],
                'meta' => ['from', 'to', 'period'],
            ]);
    }

    public function test_reading_plan_funnel_requires_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.reading-plans.funnel', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ]))
            ->assertStatus(403);
    }

    public function test_reading_plan_funnel_returns_shape(): void
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.reading-plans.funnel', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['started', 'completed_per_day', 'abandoned', 'abandoned_at_day', 'completed'],
                'meta' => ['from', 'to', 'period', 'plan_id'],
            ]);
    }

    public function test_bible_version_usage_returns_shape(): void
    {
        $super = User::factory()->super()->create();
        $token = $super->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.analytics.bible.version-usage', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'period' => 'day',
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['from', 'to', 'period'],
            ]);
    }
}
