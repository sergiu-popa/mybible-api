<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Collections;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowCollectionTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_collection_with_nested_topics(): void
    {
        $collection = Collection::factory()->forLanguage(Language::En)->create([
            'slug' => 'topical-references',
            'name' => 'Topical references',
        ]);
        $topic = CollectionTopic::factory()->english()->create([
            'collection_id' => $collection->id,
            'name' => 'Patience',
            'image_cdn_url' => 'https://cdn.example.com/p.png',
            'position' => 1,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['collection' => $collection->slug]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'slug', 'name', 'language', 'position',
                    'topics' => [['id', 'name', 'description', 'image_url', 'position']],
                ],
            ])
            ->assertJsonPath('data.slug', 'topical-references')
            ->assertJsonPath('data.topics.0.id', $topic->id)
            ->assertJsonPath('data.topics.0.image_url', 'https://cdn.example.com/p.png');
    }

    public function test_it_returns_404_for_unknown_slug(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('collections.show', ['collection' => 'no-such']))
            ->assertNotFound();
    }
}
