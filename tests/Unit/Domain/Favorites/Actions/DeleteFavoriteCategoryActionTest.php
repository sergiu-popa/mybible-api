<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\DeleteFavoriteCategoryAction;
use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteFavoriteCategoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_category_and_nulls_favorites_category_id(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $category->id]);

        $action = $this->app->make(DeleteFavoriteCategoryAction::class);

        $action->execute($category);

        $this->assertSoftDeleted('favorite_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'category_id' => null,
        ]);
    }

    public function test_it_nulls_category_id_on_soft_deleted_favorites(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $category->id]);
        $favorite->delete();

        $action = $this->app->make(DeleteFavoriteCategoryAction::class);
        $action->execute($category);

        /** @var Favorite $reloaded */
        $reloaded = Favorite::withTrashed()->findOrFail($favorite->id);
        $this->assertNull($reloaded->category_id);
        $this->assertNotNull($reloaded->deleted_at);
    }

    public function test_it_leaves_unrelated_favorites_alone(): void
    {
        $user = User::factory()->create();
        $catA = FavoriteCategory::factory()->for($user)->create();
        $catB = FavoriteCategory::factory()->for($user)->create();
        $favA = Favorite::factory()->for($user)->create(['category_id' => $catA->id]);
        $favB = Favorite::factory()->for($user)->create(['category_id' => $catB->id]);

        $action = $this->app->make(DeleteFavoriteCategoryAction::class);
        $action->execute($catA);

        $this->assertDatabaseHas('favorites', ['id' => $favA->id, 'category_id' => null]);
        $this->assertDatabaseHas('favorites', ['id' => $favB->id, 'category_id' => $catB->id]);
    }
}
