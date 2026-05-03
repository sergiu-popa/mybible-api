<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Collections;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowCollectionTopicTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        $this->collection = Collection::factory()->forLanguage(Language::En)->create([
            'slug' => 'topical-references',
            'name' => 'Topical references',
        ]);
    }

    public function test_it_returns_topic_with_all_references_parsed(): void
    {
        $topic = CollectionTopic::factory()->english()->create([
            'collection_id' => $this->collection->id,
            'name' => 'Verses about patience',
            'description' => 'Be patient.',
        ]);
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
            'position' => 1,
        ]);
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:2-3.VDC',
            'position' => 2,
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.topics.show', [
                'collection' => $this->collection->slug,
                'topic' => $topic->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'description', 'image_url', 'language', 'collection_id',
                    'references' => [['raw', 'parsed', 'display_text', 'parse_error']],
                ],
            ])
            ->assertJsonPath('data.id', $topic->id)
            ->assertJsonPath('data.name', 'Verses about patience')
            ->assertJsonCount(2, 'data.references')
            ->assertJsonPath('data.references.0.raw', 'GEN.1:1.VDC');
    }

    public function test_it_degrades_gracefully_when_one_reference_is_malformed(): void
    {
        $spy = Log::spy();

        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $this->collection->id]);
        CollectionReference::factory()->create(['collection_topic_id' => $topic->id, 'reference' => 'GEN.1:1.VDC', 'position' => 1]);
        CollectionReference::factory()->malformed()->create(['collection_topic_id' => $topic->id, 'position' => 2]);
        CollectionReference::factory()->create(['collection_topic_id' => $topic->id, 'reference' => 'GEN.1:5.VDC', 'position' => 3]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.topics.show', ['collection' => $this->collection->slug, 'topic' => $topic->id]));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data.references')
            ->assertJsonPath('data.references.0.parse_error', null)
            ->assertJsonPath('data.references.2.parse_error', null);

        /** @phpstan-ignore-next-line method.notFound */
        $spy->shouldHaveReceived('warning')->once();
    }

    public function test_it_returns_404_for_topic_in_different_collection(): void
    {
        $other = Collection::factory()->forLanguage(Language::En)->create(['slug' => 'other']);
        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $other->id]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.topics.show', [
                'collection' => $this->collection->slug,
                'topic' => $topic->id,
            ]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_topic(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.topics.show', [
                'collection' => $this->collection->slug,
                'topic' => 999_999,
            ]))
            ->assertNotFound();
    }

    public function test_it_emits_public_cache_headers(): void
    {
        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $this->collection->id]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.topics.show', ['collection' => $this->collection->slug, 'topic' => $topic->id]))
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $this->collection->id]);

        $this->getJson(route('collections.topics.show', ['collection' => $this->collection->slug, 'topic' => $topic->id]))
            ->assertUnauthorized();
    }
}
