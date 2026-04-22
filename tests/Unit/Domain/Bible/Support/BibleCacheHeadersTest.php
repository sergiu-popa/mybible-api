<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\Support;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class BibleCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_version_list_emits_list_cache_control_and_strong_etag(): void
    {
        BibleVersion::factory()->count(2)->create();

        $headers = BibleCacheHeaders::forVersionList(BibleVersion::query());

        $this->assertSame('public, max-age=3600', $headers['Cache-Control']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{40}"$/', $headers['ETag']);
    }

    public function test_for_version_list_etag_is_deterministic_for_same_state(): void
    {
        BibleVersion::factory()->count(2)->create();

        $first = BibleCacheHeaders::forVersionList(BibleVersion::query());
        $second = BibleCacheHeaders::forVersionList(BibleVersion::query());

        $this->assertSame($first['ETag'], $second['ETag']);
    }

    public function test_for_version_list_etag_changes_when_updated_at_advances(): void
    {
        $versions = BibleVersion::factory()->count(2)->create();

        $before = BibleCacheHeaders::forVersionList(BibleVersion::query());

        Carbon::setTestNow(Carbon::now()->addDay());
        $versions->first()->touch();

        $after = BibleCacheHeaders::forVersionList(BibleVersion::query());

        $this->assertNotSame($before['ETag'], $after['ETag']);
    }

    public function test_for_version_list_etag_does_not_change_with_pagination(): void
    {
        BibleVersion::factory()->count(5)->create();

        $full = BibleCacheHeaders::forVersionList(BibleVersion::query());
        $paginated = BibleCacheHeaders::forVersionList(BibleVersion::query()->limit(2));

        $this->assertSame($full['ETag'], $paginated['ETag']);
    }

    public function test_for_version_export_emits_export_cache_control_and_etag(): void
    {
        $version = BibleVersion::factory()->create();

        $headers = BibleCacheHeaders::forVersionExport($version);

        $this->assertSame('public, max-age=86400', $headers['Cache-Control']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{40}"$/', $headers['ETag']);
    }

    public function test_for_version_export_etag_changes_when_version_updates(): void
    {
        $version = BibleVersion::factory()->create();
        $before = BibleCacheHeaders::forVersionExport($version);

        Carbon::setTestNow(Carbon::now()->addDay());
        $version->touch();

        $after = BibleCacheHeaders::forVersionExport($version->fresh());

        $this->assertNotSame($before['ETag'], $after['ETag']);
    }
}
