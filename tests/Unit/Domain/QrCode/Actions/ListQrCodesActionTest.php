<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrCode\Actions;

use App\Domain\QrCode\Actions\ListQrCodesAction;
use App\Domain\QrCode\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ListQrCodesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_miss_queries_the_database(): void
    {
        QrCode::factory()->count(2)->create();

        $action = $this->app->make(ListQrCodesAction::class);

        DB::enableQueryLog();
        $result = $action->execute();
        $missQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty(
            array_filter($missQueries, static fn (array $e): bool => str_contains((string) $e['query'], 'qr_codes')),
            'Cache miss must hit the qr_codes table.',
        );
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function test_cache_hit_returns_cached_payload_without_querying(): void
    {
        QrCode::factory()->count(2)->create();

        $action = $this->app->make(ListQrCodesAction::class);

        // Warm
        $first = $action->execute();

        DB::enableQueryLog();
        $second = $action->execute();
        $hitQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertEmpty(
            array_filter($hitQueries, static fn (array $e): bool => str_contains((string) $e['query'], 'qr_codes')),
            'Cache hit must not query qr_codes.',
        );
        $this->assertSame($first, $second);
    }

    public function test_flushing_qr_tag_invalidates_the_cache(): void
    {
        $action = $this->app->make(ListQrCodesAction::class);

        QrCode::factory()->count(1)->create();
        $action->execute();

        QrCode::factory()->count(1)->create();
        Cache::tags(['qr'])->flush();

        $result = $action->execute();
        $this->assertCount(2, $result['data']);
    }
}
