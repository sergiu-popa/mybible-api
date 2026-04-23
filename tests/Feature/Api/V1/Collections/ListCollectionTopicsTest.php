<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Collections;

use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListCollectionTopicsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_topics_for_the_default_language(): void
    {
        $english = CollectionTopic::factory()->english()->create(['position' => 1]);
        CollectionTopic::factory()->romanian()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $english->id)
            ->assertJsonPath('data.0.language', 'en');
    }

    public function test_it_filters_by_explicit_language(): void
    {
        CollectionTopic::factory()->english()->create();
        $romanian = CollectionTopic::factory()->romanian()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index', ['language' => 'ro']));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $romanian->id)
            ->assertJsonPath('data.0.language', 'ro');
    }

    public function test_it_includes_reference_count(): void
    {
        $topic = CollectionTopic::factory()->english()->create();
        CollectionReference::factory()->count(3)->create([
            'collection_topic_id' => $topic->id,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reference_count', 3);
    }

    public function test_it_returns_expected_shape(): void
    {
        CollectionTopic::factory()->english()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'description', 'language', 'reference_count'],
                ],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_orders_by_position(): void
    {
        $second = CollectionTopic::factory()->english()->create(['position' => 10]);
        $first = CollectionTopic::factory()->english()->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_it_honours_a_valid_per_page(): void
    {
        CollectionTopic::factory()->english()->count(5)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index', ['per_page' => 3]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_it_caps_per_page_validation_at_100(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_emits_public_cache_headers(): void
    {
        CollectionTopic::factory()->english()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this->getJson(route('collections.index'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_unknown_api_key(): void
    {
        $this->withHeader('X-Api-Key', 'nope')
            ->getJson(route('collections.index'))
            ->assertUnauthorized();
    }
}
