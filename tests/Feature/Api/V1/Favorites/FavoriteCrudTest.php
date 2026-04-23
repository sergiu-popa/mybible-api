<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class FavoriteCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_favorite_with_canonical_reference(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorites.store'), [
            'reference' => 'GEN.1:1-3.VDC',
            'note' => 'In the beginning',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reference', 'GEN.1:1-3.VDC')
            ->assertJsonPath('data.book', 'GEN')
            ->assertJsonPath('data.chapter', 1)
            ->assertJsonPath('data.verses', [1, 2, 3])
            ->assertJsonPath('data.version', 'VDC')
            ->assertJsonPath('data.note', 'In the beginning');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'reference' => 'GEN.1:1-3.VDC',
        ]);
    }

    public function test_it_creates_a_favorite_within_a_category(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson(route('favorites.store'), [
            'reference' => 'JHN.3:16.VDC',
            'category_id' => $category->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.category_id', $category->id);
    }

    public function test_it_rejects_invalid_reference(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorites.store'), [
            'reference' => 'NOT.VALID.REFERENCE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_rejects_multi_reference_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorites.store'), [
            'reference' => 'GEN.1:1.VDC;GEN.1:2.VDC',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_rejects_category_belonging_to_another_user(): void
    {
        $owner = User::factory()->create();
        $otherCategory = FavoriteCategory::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson(route('favorites.store'), [
            'reference' => 'GEN.1:1.VDC',
            'category_id' => $otherCategory->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_lists_the_callers_favorites_paginated(): void
    {
        $user = User::factory()->create();
        Favorite::factory()->for($user)->count(3)->create();
        Favorite::factory()->create(); // another user

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorites.index'))->assertOk();

        $this->assertCount(3, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_it_filters_by_category_id(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $matching = Favorite::factory()->for($user)->create(['category_id' => $category->id]);
        Favorite::factory()->for($user)->create(['category_id' => null]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorites.index', ['category' => $category->id]))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$matching->id], $ids);
    }

    public function test_it_filters_by_uncategorized(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        Favorite::factory()->for($user)->create(['category_id' => $category->id]);
        $uncategorized = Favorite::factory()->for($user)->create(['category_id' => null]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorites.index', ['category' => 'uncategorized']))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$uncategorized->id], $ids);
    }

    public function test_it_filters_by_book(): void
    {
        $user = User::factory()->create();
        $gen = Favorite::factory()->for($user)->create(['reference' => 'GEN.1:1.VDC']);
        Favorite::factory()->for($user)->create(['reference' => 'JHN.3:16.VDC']);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorites.index', ['book' => 'GEN']))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$gen->id], $ids);
    }

    public function test_it_rejects_invalid_book_filter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('favorites.index', ['book' => 'XYZ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book']);
    }

    public function test_it_updates_category_and_note(): void
    {
        $user = User::factory()->create();
        $originalCategory = FavoriteCategory::factory()->for($user)->create();
        $newCategory = FavoriteCategory::factory()->for($user)->create();

        $favorite = Favorite::factory()->for($user)->create([
            'category_id' => $originalCategory->id,
            'note' => 'old',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(route('favorites.update', $favorite), [
            'category_id' => $newCategory->id,
            'note' => 'new',
        ])
            ->assertOk()
            ->assertJsonPath('data.category_id', $newCategory->id)
            ->assertJsonPath('data.note', 'new');
    }

    public function test_it_allows_null_category_on_update(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $category->id]);

        Sanctum::actingAs($user);

        $this->patchJson(route('favorites.update', $favorite), [
            'category_id' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.category_id', null);
    }

    public function test_it_rejects_changing_the_reference_on_update(): void
    {
        $user = User::factory()->create();
        $favorite = Favorite::factory()->for($user)->create(['reference' => 'GEN.1:1.VDC']);

        Sanctum::actingAs($user);

        $this->patchJson(route('favorites.update', $favorite), [
            'reference' => 'JHN.3:16.VDC',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_returns_403_when_updating_another_users_favorite(): void
    {
        $other = User::factory()->create();
        $favorite = Favorite::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('favorites.update', $favorite), ['note' => 'hijack'])
            ->assertForbidden();
    }

    public function test_it_deletes_a_favorite(): void
    {
        $user = User::factory()->create();
        $favorite = Favorite::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('favorites.destroy', $favorite))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }

    public function test_it_returns_403_when_deleting_another_users_favorite(): void
    {
        $other = User::factory()->create();
        $favorite = Favorite::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('favorites.destroy', $favorite))
            ->assertForbidden();
    }

    public function test_it_returns_401_when_unauthenticated(): void
    {
        $this->getJson(route('favorites.index'))->assertUnauthorized();
    }
}
