<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\QrCode;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowQrCodeTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_the_qr_metadata_for_a_known_reference(): void
    {
        QrCode::factory()->create([
            'reference' => 'GEN.1:1.VDC',
            'url' => 'https://web.example/gen-1-1',
            'image_path' => 'gen-1-1.png',
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show', ['reference' => 'GEN.1:1.VDC']));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['reference', 'url', 'image_url'],
            ])
            ->assertJsonPath('data.reference', 'GEN.1:1.VDC')
            ->assertJsonPath('data.url', 'https://web.example/gen-1-1');

        $this->assertStringContainsString('gen-1-1.png', (string) $response->json('data.image_url'));
    }

    public function test_it_returns_404_when_no_stored_qr_exists(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show', ['reference' => 'JHN.3:16.VDC']))
            ->assertNotFound();
    }

    public function test_it_returns_422_for_an_unparseable_reference(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show', ['reference' => 'not a reference']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_it_returns_422_for_a_multi_reference_input(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show', ['reference' => 'GEN.1-3.VDC']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_it_requires_the_reference_parameter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_it_sets_public_cache_headers(): void
    {
        QrCode::factory()->forReference('GEN.1:1.VDC')->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('qr-codes.show', ['reference' => 'GEN.1:1.VDC']));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }

    public function test_it_accepts_sanctum_auth(): void
    {
        QrCode::factory()->forReference('GEN.1:1.VDC')->create();

        $this->givenAnAuthenticatedUser();

        $this->getJson(route('qr-codes.show', ['reference' => 'GEN.1:1.VDC']))
            ->assertOk();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('qr-codes.show', ['reference' => 'GEN.1:1.VDC']))
            ->assertUnauthorized();
    }
}
