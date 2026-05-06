<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Analytics;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\QrCode\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ServerSideEmissionsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_login_emits_auth_login_event(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ])->assertOk();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'auth.login')->count(),
        );
    }

    public function test_qr_code_scan_emits_event(): void
    {
        $qr = QrCode::factory()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('qr-codes.scans.store', ['qr' => $qr->id]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'qr_code.scanned')->count(),
        );
    }

    public function test_resource_download_emits_event(): void
    {
        $resource = EducationalResource::factory()->create();

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'd1'])
            ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'resource.downloaded')->count(),
        );
    }
}
