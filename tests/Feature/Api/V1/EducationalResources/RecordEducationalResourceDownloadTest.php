<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\Analytics\Events\DownloadOccurred;
use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class RecordEducationalResourceDownloadTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
        RateLimiter::clear('downloads');
    }

    public function test_anonymous_request_records_a_download(): void
    {
        Event::fake([DownloadOccurred::class]);

        $resource = EducationalResource::factory()->create();

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'device-123'])
            ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]), [])
            ->assertNoContent();

        $this->assertDatabaseHas('resource_downloads', [
            'downloadable_type' => ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
            'downloadable_id' => $resource->id,
            'device_id' => 'device-123',
            'user_id' => null,
        ]);

        Event::assertDispatched(DownloadOccurred::class);
    }

    public function test_authenticated_request_captures_user_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $resource = EducationalResource::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])
            ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]), [])
            ->assertNoContent();

        $this->assertDatabaseHas('resource_downloads', [
            'downloadable_type' => ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
            'downloadable_id' => $resource->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_returns_404_for_unknown_resource(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('resources.downloads.store', ['resource' => '00000000-0000-0000-0000-000000000000']), [])
            ->assertNotFound();
    }

    public function test_rate_limit_triggers_at_61st_request_for_same_device(): void
    {
        $resource = EducationalResource::factory()->create();
        $headers = $this->apiKeyHeaders() + ['X-Device-Id' => 'rate-limit-device'];

        for ($i = 1; $i <= 60; $i++) {
            $this->withHeaders($headers)
                ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]), [])
                ->assertNoContent();
        }

        $this->withHeaders($headers)
            ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]), [])
            ->assertStatus(429);
    }
}
