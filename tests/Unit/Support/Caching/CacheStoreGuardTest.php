<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caching;

use App\Support\Caching\CacheStoreGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

final class CacheStoreGuardTest extends TestCase
{
    public function test_it_passes_when_the_default_store_is_taggable(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('cache.default', 'array');
        Cache::clearResolvedInstances();

        CacheStoreGuard::ensureTaggable();
    }

    public function test_it_throws_when_the_default_store_does_not_support_tags(): void
    {
        Config::set('cache.default', 'file');
        Cache::clearResolvedInstances();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support tags');

        CacheStoreGuard::ensureTaggable();
    }
}
