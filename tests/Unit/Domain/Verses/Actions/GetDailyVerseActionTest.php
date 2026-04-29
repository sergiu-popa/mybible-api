<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Verses\Actions;

use App\Domain\Verses\Actions\GetDailyVerseAction;
use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Domain\Verses\Models\DailyVerse;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetDailyVerseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_daily_verse_for_a_known_date(): void
    {
        DailyVerse::factory()->create([
            'for_date' => '2025-01-01',
            'reference' => 'GEN.1:1.VDC',
        ]);

        $payload = app(GetDailyVerseAction::class)->handle(new DateTimeImmutable('2025-01-01'));

        $this->assertSame('2025-01-01', $payload['data']['date']);
        $this->assertSame('GEN.1:1.VDC', $payload['data']['reference']);
    }

    public function test_it_throws_when_no_verse_is_configured_for_the_date(): void
    {
        $this->expectException(NoDailyVerseForDateException::class);

        app(GetDailyVerseAction::class)->handle(new DateTimeImmutable('2025-01-01'));
    }
}
