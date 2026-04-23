<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Mobile;

use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowMobileVersionTest extends TestCase
{
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        config()->set('mobile', [
            'ios' => [
                'minimum_supported_version' => '3.2.0',
                'latest_version' => '3.4.1',
                'update_url' => 'https://apps.apple.com/app/id1',
                'force_update_below' => '3.0.0',
            ],
            'android' => [
                'minimum_supported_version' => '3.1.0',
                'latest_version' => '3.4.2',
                'update_url' => 'https://play.google.com/store/apps/details?id=app',
                'force_update_below' => '2.9.0',
            ],
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
}
