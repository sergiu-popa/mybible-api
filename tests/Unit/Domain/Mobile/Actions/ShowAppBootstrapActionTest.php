<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Actions;

use App\Domain\Mobile\Actions\ShowAppBootstrapAction;
use App\Domain\Mobile\Support\MobileCacheKeys;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-level coverage for the bootstrap composition. End-to-end HTTP flow
 * is covered by ShowAppBootstrapTest; this test focuses on the Action's
 * cache key/tag wiring against a real (empty) database.
 */
final class ShowAppBootstrapActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mobile', [
            'ios' => ['latest_version' => '9.9.9'],
            'android' => ['latest_version' => '8.8.8'],
            'bootstrap' => ['cache_ttl' => 300],
        ]);
    }

    public function test_it_composes_payload_with_all_top_level_keys(): void
    {
        $action = $this->app->make(ShowAppBootstrapAction::class);

        $payload = $action->execute(Language::Ro);

        $this->assertSame('9.9.9', $payload['version']['ios']);
        $this->assertSame('8.8.8', $payload['version']['android']);
        $this->assertContains('ro', $payload['languages_available']);
        $this->assertNull($payload['daily_verse']);
        $this->assertSame([], $payload['news']);
        $this->assertSame([], $payload['bible_versions']);
        $this->assertNull($payload['devotionals_today']['adults']);
        $this->assertNull($payload['devotionals_today']['youth']);
        $this->assertNull($payload['sabbath_school_current_lesson']);
        $this->assertSame([], $payload['qr_codes']);
    }

    public function test_payload_is_cached_under_the_bootstrap_key(): void
    {
        $action = $this->app->make(ShowAppBootstrapAction::class);

        $action->execute(Language::Ro);

        $key = MobileCacheKeys::bootstrap(Language::Ro);
        $cached = Cache::tags(MobileCacheKeys::tagsForBootstrap())->get($key);

        $this->assertNotNull(
            $cached,
            "After execute(), the bootstrap payload must be present at cache key {$key}.",
        );
    }

    public function test_flushing_any_constituent_tag_busts_the_bootstrap_entry(): void
    {
        $action = $this->app->make(ShowAppBootstrapAction::class);

        $action->execute(Language::En);
        $key = MobileCacheKeys::bootstrap(Language::En);
        $tags = MobileCacheKeys::tagsForBootstrap();

        $this->assertNotNull(
            Cache::tags($tags)->get($key),
            'Bootstrap entry should be present after warm-up.',
        );

        Cache::tags(['news'])->flush();

        $this->assertNull(
            Cache::tags($tags)->get($key),
            'Flushing the `news` tag must invalidate the bootstrap entry.',
        );
    }

    public function test_payload_includes_every_iso2_language_value(): void
    {
        $action = $this->app->make(ShowAppBootstrapAction::class);
        $payload = $action->execute(Language::En);

        $expected = array_map(static fn (Language $l): string => $l->value, Language::cases());
        $this->assertSame($expected, $payload['languages_available']);
    }
}
