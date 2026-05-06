<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Analytics;

use App\Domain\Analytics\Actions\ComputeDauMauAction;
use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use App\Domain\Analytics\Models\AnalyticsUserActiveDaily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ComputeDauMauActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_28_day_rolling_mau_equals_distinct_users_in_window(): void
    {
        // 30 distinct users, each active on a different day in April.
        for ($day = 1; $day <= 30; $day++) {
            AnalyticsUserActiveDaily::factory()->create([
                'date' => sprintf('2026-04-%02d', $day),
                'user_id' => $day,
            ]);
        }

        $query = new AnalyticsRangeQueryData(
            from: CarbonImmutable::parse('2026-04-30'),
            to: CarbonImmutable::parse('2026-04-30'),
            period: 'day',
        );

        $rows = app(ComputeDauMauAction::class)->execute($query);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-04-30', $rows[0]['date']);
        $this->assertSame(1, $rows[0]['dau_users']);
        // Window is [2026-04-03, 2026-04-30] = 28 distinct users.
        $this->assertSame(28, $rows[0]['mau_users']);
    }
}
