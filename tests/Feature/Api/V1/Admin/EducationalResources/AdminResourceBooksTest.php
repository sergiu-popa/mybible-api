<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminResourceBooksTest extends TestCase
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

    public function test_list_includes_drafts_for_super_admin(): void
    {
        $this->actingAsSuper();

        ResourceBook::factory()->draft()->forLanguage(Language::Ro)->create();
        ResourceBook::factory()->published()->forLanguage(Language::Ro)->create();

        $this->getJson(route('admin.resource-books.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_blocked_for_non_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.resource-books.index'))->assertForbidden();
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson(route('admin.resource-books.index'))->assertUnauthorized();
    }

    public function test_create_persists_a_new_book(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.resource-books.store'), [
            'slug' => 'my-book',
            'name' => 'My Book',
            'language' => 'ro',
            'description' => 'Some description',
            'author' => 'Author',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'my-book')
            ->assertJsonPath('data.is_published', false);

        $this->assertDatabaseHas('resource_books', [
            'slug' => 'my-book',
            'name' => 'My Book',
            'language' => 'ro',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.resource-books.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'language']);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $this->actingAsSuper();

        ResourceBook::factory()->create(['slug' => 'taken']);

        $this->postJson(route('admin.resource-books.store'), [
            'slug' => 'taken',
            'name' => 'X',
            'language' => 'ro',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_blocked_for_non_super(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.resource-books.store'), [
            'name' => 'X',
            'language' => 'ro',
        ])->assertForbidden();
    }

    public function test_update_modifies_metadata(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create(['author' => 'Old']);

        $this->patchJson(route('admin.resource-books.update', ['book' => $book->id]), [
            'author' => 'New',
        ])->assertOk()
            ->assertJsonPath('data.author', 'New');

        $this->assertSame('New', $book->fresh()?->author);
    }

    public function test_update_rejects_slug_collision(): void
    {
        $this->actingAsSuper();

        $a = ResourceBook::factory()->create(['slug' => 'a']);
        $b = ResourceBook::factory()->create(['slug' => 'b']);

        $this->patchJson(route('admin.resource-books.update', ['book' => $a->id]), [
            'slug' => $b->slug,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_ignores_published_at_field(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->published()->create();
        $original = $book->published_at;

        $this->patchJson(route('admin.resource-books.update', ['book' => $book->id]), [
            'published_at' => null,
            'author' => 'Renamed',
        ])->assertOk();

        $fresh = $book->fresh();
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->published_at);
        $this->assertEquals($original?->toIso8601String(), $fresh->published_at->toIso8601String());
        $this->assertSame('Renamed', $fresh->author);
    }

    public function test_publish_and_unpublish_round_trip(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->draft()->create();

        $this->postJson(route('admin.resource-books.publish', ['book' => $book->id]))
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->assertTrue((bool) $book->fresh()->is_published);
        $this->assertNotNull($book->fresh()?->published_at);

        $this->postJson(route('admin.resource-books.unpublish', ['book' => $book->id]))
            ->assertOk()
            ->assertJsonPath('data.is_published', false);
    }

    public function test_delete_soft_deletes(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();

        $this->deleteJson(route('admin.resource-books.destroy', ['book' => $book->id]))
            ->assertNoContent();

        $this->assertSoftDeleted('resource_books', ['id' => $book->id]);
    }

    public function test_reorder_within_language_assigns_positions(): void
    {
        $this->actingAsSuper();

        $a = ResourceBook::factory()->forLanguage(Language::Ro)->create();
        $b = ResourceBook::factory()->forLanguage(Language::Ro)->create();

        $this->postJson(route('admin.resource-books.reorder'), [
            'language' => 'ro',
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(1, (int) $b->fresh()?->position);
        $this->assertSame(2, (int) $a->fresh()?->position);
    }

    public function test_reorder_rejects_cross_language_ids(): void
    {
        $this->actingAsSuper();

        $ro = ResourceBook::factory()->forLanguage(Language::Ro)->create();
        $en = ResourceBook::factory()->forLanguage(Language::En)->create();

        $this->postJson(route('admin.resource-books.reorder'), [
            'language' => 'ro',
            'ids' => [$ro->id, $en->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }
}
