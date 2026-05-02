<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Mobile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowAppBootstrapTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        config()->set('mobile', [
            'ios' => ['latest_version' => '3.4.1'],
            'android' => ['latest_version' => '3.4.2'],
            'bootstrap' => ['cache_ttl' => 300],
        ]);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('app.bootstrap'))
            ->assertUnauthorized();
    }

    public function test_it_returns_all_expected_top_level_keys(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'version' => ['ios', 'android'],
                    'languages_available',
                    'daily_verse',
                    'news',
                    'bible_versions',
                    'devotionals_today' => ['adults', 'youth'],
                    'sabbath_school_current_lesson',
                    'qr_codes',
                ],
            ]);
    }

    public function test_it_returns_version_from_config(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'));

        $response->assertOk()
            ->assertJsonPath('data.version.ios', '3.4.1')
            ->assertJsonPath('data.version.android', '3.4.2');
    }

    public function test_it_returns_available_languages(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'));

        $languages = $response->json('data.languages_available');
        $this->assertContains('en', $languages);
        $this->assertContains('ro', $languages);
        $this->assertContains('hu', $languages);
    }

    public function test_it_accepts_valid_language_parameter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap', ['language' => 'ro']))
            ->assertOk();
    }

    public function test_it_rejects_invalid_language_parameter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap', ['language' => 'xx']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('language');
    }

    public function test_it_sets_public_cache_control_header(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }

    public function test_cache_hit_on_second_request_issues_zero_db_queries(): void
    {
        // Warm the cache.
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'))
            ->assertOk();

        DB::enableQueryLog();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'))
            ->assertOk();

        $appQueries = array_values(array_filter(
            DB::getQueryLog(),
            // Sanctum/api-key auth and route-bind queries still count, but the
            // bootstrap aggregator itself must hit zero application tables.
            // Filter to the constituent tables we care about.
            static fn (array $entry): bool => (bool) preg_match(
                '/\b(news|daily_verses|bible_versions|devotionals|sabbath_school_lessons|qr_codes)\b/i',
                (string) $entry['query'],
            ),
        ));
        DB::disableQueryLog();

        $this->assertCount(
            0,
            $appQueries,
            'Cache hit must not query application tables; got: '
                . implode("\n", array_column($appQueries, 'query')),
        );
    }

    public function test_flushing_news_tag_busts_the_bootstrap_cache(): void
    {
        // Warm the cache.
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'))
            ->assertOk();

        Cache::tags(['news'])->flush();

        DB::enableQueryLog();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'))
            ->assertOk();

        $rebuildHitDb = false;
        foreach (DB::getQueryLog() as $entry) {
            if (preg_match('/\b(news|daily_verses|bible_versions|devotionals|sabbath_school_lessons|qr_codes)\b/i', (string) $entry['query']) === 1) {
                $rebuildHitDb = true;
                break;
            }
        }
        DB::disableQueryLog();

        $this->assertTrue(
            $rebuildHitDb,
            'Flushing the `news` tag must invalidate the bootstrap cache, forcing a rebuild that re-queries the constituents.',
        );
    }

    public function test_null_values_are_returned_when_no_data_seeded(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('app.bootstrap'));

        $response->assertOk();
        $this->assertNull($response->json('data.daily_verse'));
        $this->assertNull($response->json('data.devotionals_today.adults'));
        $this->assertNull($response->json('data.devotionals_today.youth'));
        $this->assertNull($response->json('data.sabbath_school_current_lesson'));
        $this->assertIsArray($response->json('data.news'));
        $this->assertIsArray($response->json('data.bible_versions'));
        $this->assertIsArray($response->json('data.qr_codes'));
    }
}
