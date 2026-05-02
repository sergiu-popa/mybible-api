<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class FavoriteCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_callers_categories_ordered_by_name(): void
    {
        $user = User::factory()->create();
        FavoriteCategory::factory()->for($user)->create(['name' => 'Zeal']);
        FavoriteCategory::factory()->for($user)->create(['name' => 'Amen']);
        FavoriteCategory::factory()->create(['name' => 'Other user']); // different user

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite-categories.index'))
            ->assertOk();

        $names = array_column($response->json('data'), 'name');
        $this->assertSame(['Amen', 'Zeal'], $names);
    }

    public function test_it_prepends_the_uncategorized_synthetic_entry_when_applicable(): void
    {
        $user = User::factory()->create();
        FavoriteCategory::factory()->for($user)->create(['name' => 'Hope']);
        Favorite::factory()->for($user)->create(['category_id' => null]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite-categories.index'))->assertOk();

        $data = $response->json('data');

        $this->assertSame(null, $data[0]['id']);
        $this->assertSame('Uncategorized', $data[0]['name']);
        $this->assertSame(1, $data[0]['favorites_count']);
        $this->assertSame('Hope', $data[1]['name']);
    }

    public function test_it_omits_the_uncategorized_entry_when_no_null_favorites_exist(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        Favorite::factory()->for($user)->create(['category_id' => $category->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite-categories.index'))->assertOk();

        foreach ($response->json('data') as $row) {
            $this->assertNotNull($row['id']);
        }
    }

    public function test_it_creates_a_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorite-categories.store'), [
            'name' => 'Memorize',
            'color' => '#FF0000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Memorize')
            ->assertJsonPath('data.color', '#FF0000')
            ->assertJsonPath('data.favorites_count', 0);

        $this->assertDatabaseHas('favorite_categories', [
            'user_id' => $user->id,
            'name' => 'Memorize',
            'color' => '#FF0000',
        ]);
    }

    public function test_it_rejects_duplicate_name_for_same_user(): void
    {
        $user = User::factory()->create();
        FavoriteCategory::factory()->for($user)->create(['name' => 'Memorize']);

        Sanctum::actingAs($user);

        $this->postJson(route('favorite-categories.store'), ['name' => 'Memorize'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_allows_same_name_across_different_users(): void
    {
        $otherUser = User::factory()->create();
        FavoriteCategory::factory()->for($otherUser)->create(['name' => 'Memorize']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorite-categories.store'), ['name' => 'Memorize'])
            ->assertCreated();
    }

    public function test_it_rejects_invalid_color(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('favorite-categories.store'), [
            'name' => 'Bad',
            'color' => 'notahex',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    public function test_it_updates_a_category(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create(['name' => 'Old']);

        Sanctum::actingAs($user);

        $this->patchJson(route('favorite-categories.update', $category), [
            'name' => 'New',
            'color' => '#123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.color', '#123456');
    }

    public function test_it_returns_403_when_updating_another_users_category(): void
    {
        $other = User::factory()->create();
        $category = FavoriteCategory::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('favorite-categories.update', $category), ['name' => 'Hijack'])
            ->assertForbidden();
    }

    public function test_it_returns_401_when_unauthenticated(): void
    {
        $this->getJson(route('favorite-categories.index'))->assertUnauthorized();
    }

    public function test_it_deletes_a_category_and_cascades_favorites_to_uncategorized(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $category->id]);

        Sanctum::actingAs($user);

        $this->deleteJson(route('favorite-categories.destroy', $category))
            ->assertNoContent();

        $this->assertSoftDeleted('favorite_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'category_id' => null,
        ]);
    }

    public function test_it_returns_403_when_deleting_another_users_category(): void
    {
        $other = User::factory()->create();
        $category = FavoriteCategory::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('favorite-categories.destroy', $category))
            ->assertForbidden();
    }
}
