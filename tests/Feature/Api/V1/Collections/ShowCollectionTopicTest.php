<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Collections;

use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowCollectionTopicTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_topic_with_all_references_parsed(): void
    {
        $topic = CollectionTopic::factory()->english()->create([
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
            ->getJson(route('collections.show', ['topic' => $topic->id]));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'language',
                    'references' => [
                        [
                            'raw',
                            'parsed',
                            'display_text',
                            'parse_error',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $topic->id)
            ->assertJsonPath('data.name', 'Verses about patience')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonCount(2, 'data.references')
            ->assertJsonPath('data.references.0.raw', 'GEN.1:1.VDC')
            ->assertJsonPath('data.references.0.parse_error', null)
            ->assertJsonPath('data.references.0.parsed.0.book', 'GEN')
            ->assertJsonPath('data.references.0.parsed.0.chapter', 1)
            ->assertJsonPath('data.references.0.parsed.0.verses', [1])
            ->assertJsonPath('data.references.0.parsed.0.version', 'VDC');
    }

    public function test_it_degrades_gracefully_when_one_reference_is_malformed(): void
    {
        $spy = Log::spy();

        $topic = CollectionTopic::factory()->english()->create();
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
            'position' => 1,
        ]);
        CollectionReference::factory()->malformed()->create([
            'collection_topic_id' => $topic->id,
            'position' => 2,
        ]);
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:5.VDC',
            'position' => 3,
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['topic' => $topic->id]));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data.references')
            ->assertJsonPath('data.references.0.parse_error', null)
            ->assertJsonPath('data.references.2.parse_error', null);

        $malformed = $response->json('data.references.1');
        $this->assertIsString($malformed['parse_error']);
        $this->assertNotSame('', $malformed['parse_error']);
        $this->assertNull($malformed['parsed']);
        $this->assertNull($malformed['display_text']);

        /** @phpstan-ignore-next-line method.notFound */
        $spy->shouldHaveReceived('warning')->once();
    }

    public function test_it_returns_404_for_cross_language_topic(): void
    {
        $topic = CollectionTopic::factory()->english()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['topic' => $topic->id, 'language' => 'ro']))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_topic(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['topic' => 999_999]))
            ->assertNotFound();
    }

    public function test_it_emits_public_cache_headers(): void
    {
        $topic = CollectionTopic::factory()->english()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['topic' => $topic->id]))
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $topic = CollectionTopic::factory()->english()->create();

        $this->getJson(route('collections.show', ['topic' => $topic->id]))
            ->assertUnauthorized();
    }

    public function test_it_orders_references_by_position(): void
    {
        $topic = CollectionTopic::factory()->english()->create();
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:3.VDC',
            'position' => 3,
        ]);
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:1.VDC',
            'position' => 1,
        ]);
        CollectionReference::factory()->create([
            'collection_topic_id' => $topic->id,
            'reference' => 'GEN.1:2.VDC',
            'position' => 2,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['topic' => $topic->id]))
            ->assertOk()
            ->assertJsonPath('data.references.0.raw', 'GEN.1:1.VDC')
            ->assertJsonPath('data.references.1.raw', 'GEN.1:2.VDC')
            ->assertJsonPath('data.references.2.raw', 'GEN.1:3.VDC');
    }
}
