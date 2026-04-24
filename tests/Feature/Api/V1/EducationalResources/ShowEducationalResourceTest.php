<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowEducationalResourceTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        config()->set('educational_resources.media_disk', 'public');
        Storage::fake('public');
    }

    public function test_it_returns_full_detail_for_a_resource(): void
    {
        $category = ResourceCategory::factory()->create([
            'name' => ['en' => 'Theology'],
        ]);

        $resource = EducationalResource::factory()->forCategory($category)->create([
            'title' => ['en' => 'Deep Dive'],
            'summary' => ['en' => 'A short summary'],
            'content' => ['en' => 'Full body text.'],
            'author' => 'Jane Author',
            'type' => ResourceType::Article,
            'thumbnail_path' => 'resources/thumb.jpg',
            'media_path' => 'resources/video.mp4',
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resources.show', ['resource' => $resource->uuid]))
            ->assertOk();

        $response
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'type',
                    'title',
                    'summary',
                    'content',
                    'thumbnail_url',
                    'media_url',
                    'author',
                    'published_at',
                    'category' => ['id', 'name'],
                ],
            ])
            ->assertJsonPath('data.uuid', $resource->uuid)
            ->assertJsonPath('data.type', 'article')
            ->assertJsonPath('data.title', 'Deep Dive')
            ->assertJsonPath('data.summary', 'A short summary')
            ->assertJsonPath('data.content', 'Full body text.')
            ->assertJsonPath('data.author', 'Jane Author')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Theology');

        $thumbnailUrl = (string) $response->json('data.thumbnail_url');
        $mediaUrl = (string) $response->json('data.media_url');

        // The resolver delegates to Storage::disk(...)->url(...) — we
        // validate it returned something that points at the stored path
        // rather than leaking the raw DB column. Storage::fake() returns a
        // disk-prefixed path; the real S3/public disk returns a fully
        // qualified URL.
        $this->assertNotSame('resources/thumb.jpg', $thumbnailUrl);
        $this->assertNotSame('resources/video.mp4', $mediaUrl);
        $this->assertStringContainsString('resources/thumb.jpg', $thumbnailUrl);
        $this->assertStringContainsString('resources/video.mp4', $mediaUrl);
    }

    public function test_it_returns_null_media_urls_when_paths_are_missing(): void
    {
        $category = ResourceCategory::factory()->create();
        $resource = EducationalResource::factory()->forCategory($category)->create([
            'thumbnail_path' => null,
            'media_path' => null,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resources.show', ['resource' => $resource->uuid]))
            ->assertOk()
            ->assertJsonPath('data.thumbnail_url', null)
            ->assertJsonPath('data.media_url', null);
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resources.show', ['resource' => (string) Str::uuid()]))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_it_does_not_accept_lookup_by_integer_id(): void
    {
        $category = ResourceCategory::factory()->create();
        $resource = EducationalResource::factory()->forCategory($category)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resources.show', ['resource' => (string) $resource->id]))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $category = ResourceCategory::factory()->create();
        $resource = EducationalResource::factory()->forCategory($category)->create();

        $this->getJson(route('resources.show', ['resource' => $resource->uuid]))
            ->assertUnauthorized();
    }
}
