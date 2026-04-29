<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caching;

use App\Support\Caching\CachedRead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class CachedReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::store()->clear();
    }

    public function test_it_returns_the_built_value_on_a_cache_miss(): void
    {
        $cache = new CachedRead;

        $built = $cache->read('test:key', ['t1'], 60, fn (): array => ['hello' => 'world']);

        $this->assertSame(['hello' => 'world'], $built);
    }

    public function test_it_serves_from_cache_on_subsequent_reads_without_running_the_builder(): void
    {
        $cache = new CachedRead;

        $cache->read('test:key', ['t1'], 60, fn (): array => ['build_count' => 1]);

        $count = 0;
        $second = $cache->read('test:key', ['t1'], 60, function () use (&$count): array {
            $count++;

            return ['build_count' => 999];
        });

        $this->assertSame(0, $count);
        $this->assertSame(['build_count' => 1], $second);
    }

    public function test_it_logs_cache_miss_on_a_cold_read(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('cache.miss', ['key' => 'test:miss']);

        $cache = new CachedRead;
        $cache->read('test:miss', ['t1'], 60, fn (): array => ['v' => 1]);
    }

    public function test_it_does_not_log_on_a_cache_hit(): void
    {
        $cache = new CachedRead;
        $cache->read('test:hit', ['t1'], 60, fn (): array => ['v' => 1]);

        Log::shouldReceive('info')->never();

        $cache->read('test:hit', ['t1'], 60, fn (): array => ['v' => 2]);
    }

    public function test_a_tag_flush_forces_a_rebuild(): void
    {
        $cache = new CachedRead;
        $build = 0;

        $first = $cache->read('test:invalidate', ['ss:lessons'], 60, function () use (&$build): array {
            $build++;

            return ['n' => $build];
        });

        Cache::tags(['ss:lessons'])->flush();

        $second = $cache->read('test:invalidate', ['ss:lessons'], 60, function () use (&$build): array {
            $build++;

            return ['n' => $build];
        });

        $this->assertSame(['n' => 1], $first);
        $this->assertSame(['n' => 2], $second);
    }
}
