<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Collections;

use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_blocks_non_super_admin(): void
    {
        $this->actingAsAdmin();
        $this->getJson(route('admin.collections.index'))->assertForbidden();
    }

    public function test_create_persists_collection(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.collections.store'), [
            'slug' => 'topical-references',
            'name' => 'Topical references',
            'language' => 'en',
            'position' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'topical-references');

        $this->assertDatabaseHas('collections', ['slug' => 'topical-references']);
    }

    public function test_update_changes_fields(): void
    {
        $this->actingAsSuper();

        $collection = Collection::factory()->forLanguage(Language::En)->create();

        $this->patchJson(route('admin.collections.update', ['collection' => $collection->slug]), [
            'name' => 'New name',
            'position' => 99,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.position', 99);
    }

    public function test_delete_orphans_topics_via_set_null(): void
    {
        $this->actingAsSuper();

        $collection = Collection::factory()->forLanguage(Language::En)->create();
        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $collection->id]);

        $this->deleteJson(route('admin.collections.destroy', ['collection' => $collection->slug]))
            ->assertNoContent();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('collection_topics', ['id' => $topic->id, 'collection_id' => null]);
    }

    public function test_create_topic_under_collection(): void
    {
        $this->actingAsSuper();

        $collection = Collection::factory()->forLanguage(Language::En)->create();

        $this->postJson(route('admin.collections.topics.store', ['collection' => $collection->slug]), [
            'name' => 'New topic',
            'image_cdn_url' => 'https://cdn.example.com/img.png',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New topic')
            ->assertJsonPath('data.image_url', 'https://cdn.example.com/img.png');

        $this->assertDatabaseHas('collection_topics', [
            'collection_id' => $collection->id,
            'name' => 'New topic',
            'image_cdn_url' => 'https://cdn.example.com/img.png',
        ]);
    }

    public function test_update_topic_scope_bindings_404_across_collection(): void
    {
        $this->actingAsSuper();

        $a = Collection::factory()->forLanguage(Language::En)->create();
        $b = Collection::factory()->forLanguage(Language::En)->create();
        $topic = CollectionTopic::factory()->english()->create(['collection_id' => $a->id]);

        $this->patchJson(
            route('admin.collections.topics.update', ['collection' => $b->slug, 'topic' => $topic->id]),
            ['name' => 'Renamed'],
        )->assertNotFound();
    }
}
