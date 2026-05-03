<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Collections;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

/**
 * As of MBA-027 the public `GET /api/v1/collections` endpoint returns
 * parent collections (groups of topics). The legacy topics-list shape
 * was removed; admin/frontend only consume the per-collection detail.
 */
final class ListCollectionTopicsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_collections_for_the_default_language(): void
    {
        $english = Collection::factory()->forLanguage(Language::En)
            ->create(['position' => 1, 'name' => 'Topical refs']);
        Collection::factory()->forLanguage(Language::Ro)->create();

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
        Collection::factory()->forLanguage(Language::En)->create();
        $romanian = Collection::factory()->forLanguage(Language::Ro)->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index', ['language' => 'ro']));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $romanian->id);
    }

    public function test_it_includes_topics_count(): void
    {
        $collection = Collection::factory()->forLanguage(Language::En)->create();
        CollectionTopic::factory()->english()->count(3)->create([
            'collection_id' => $collection->id,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonPath('data.0.topics_count', 3);
    }

    public function test_it_returns_expected_shape(): void
    {
        Collection::factory()->forLanguage(Language::En)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'slug', 'name', 'language', 'position', 'topics_count']],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_orders_by_position(): void
    {
        $second = Collection::factory()->forLanguage(Language::En)->create(['position' => 10]);
        $first = Collection::factory()->forLanguage(Language::En)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this->getJson(route('collections.index'))
            ->assertUnauthorized();
    }
}
