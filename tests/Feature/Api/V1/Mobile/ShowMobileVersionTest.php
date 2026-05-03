<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Mobile;

use App\Domain\Mobile\Models\MobileVersion;
use App\Domain\Mobile\Support\MobileVersionsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowMobileVersionTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        Cache::flush();
        app(MobileVersionsRepository::class)->flush();

        // Per-test fallback config so missing rows degrade safely.
        config()->set('mobile.ios.update_url', 'https://apps.apple.com/app/id1');
        config()->set('mobile.android.update_url', 'https://play.google.com/store/apps/details?id=app');
        config()->set('mobile.ios.force_update_below', '3.0.0');
        config()->set('mobile.android.force_update_below', '2.9.0');

        MobileVersion::query()->delete();
        MobileVersion::factory()->create([
            'platform' => 'ios',
            'kind' => MobileVersion::KIND_MIN_REQUIRED,
            'version' => '3.2.0',
            'store_url' => 'https://apps.apple.com/app/id1',
        ]);
        MobileVersion::factory()->create([
            'platform' => 'ios',
            'kind' => MobileVersion::KIND_LATEST,
            'version' => '3.4.1',
            'store_url' => 'https://apps.apple.com/app/id1',
        ]);
        MobileVersion::factory()->create([
            'platform' => 'android',
            'kind' => MobileVersion::KIND_MIN_REQUIRED,
            'version' => '3.1.0',
            'store_url' => 'https://play.google.com/store/apps/details?id=app',
        ]);
        MobileVersion::factory()->create([
            'platform' => 'android',
            'kind' => MobileVersion::KIND_LATEST,
            'version' => '3.4.2',
            'store_url' => 'https://play.google.com/store/apps/details?id=app',
        ]);
    }

    public function test_it_returns_ios_version_metadata(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'ios']));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'platform',
                    'minimum_supported_version',
                    'latest_version',
                    'update_url',
                    'force_update_below',
                ],
            ])
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.minimum_supported_version', '3.2.0')
            ->assertJsonPath('data.latest_version', '3.4.1')
            ->assertJsonPath('data.update_url', 'https://apps.apple.com/app/id1')
            ->assertJsonPath('data.force_update_below', '3.0.0');
    }

    public function test_it_returns_android_version_metadata(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'android']));

        $response
            ->assertOk()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.latest_version', '3.4.2')
            ->assertJsonPath('data.force_update_below', '2.9.0');
    }

    public function test_it_rejects_missing_platform(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }

    public function test_it_rejects_unknown_platform(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'windows']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }

    public function test_it_sets_public_cache_headers(): void
    {
        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'ios']));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }

    public function test_api_key_alone_is_sufficient(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'ios']))
            ->assertOk();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('mobile.version', ['platform' => 'ios']))
            ->assertUnauthorized();
    }

    public function test_it_reflects_db_updates_after_repository_flush(): void
    {
        Cache::flush();
        app(MobileVersionsRepository::class)->flush();

        MobileVersion::query()
            ->where('platform', 'ios')
            ->where('kind', MobileVersion::KIND_LATEST)
            ->update(['version' => '3.5.0']);

        Cache::flush();
        app(MobileVersionsRepository::class)->flush();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('mobile.version', ['platform' => 'ios']))
            ->assertOk()
            ->assertJsonPath('data.latest_version', '3.5.0');
    }
}
