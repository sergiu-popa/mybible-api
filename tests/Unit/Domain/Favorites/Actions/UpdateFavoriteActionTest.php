<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\UpdateFavoriteAction;
use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteData;
use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_category_when_provided(): void
    {
        $user = User::factory()->create();
        $a = FavoriteCategory::factory()->for($user)->create();
        $b = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $a->id, 'note' => 'keep me']);

        $action = $this->app->make(UpdateFavoriteAction::class);

        $action->execute(new UpdateFavoriteData(
            favorite: $favorite,
            categoryId: $b->id,
            categoryProvided: true,
            note: null,
            noteProvided: false,
        ));

        $fresh = $favorite->fresh();
        $this->assertSame($b->id, $fresh?->category_id);
        $this->assertSame('keep me', $fresh?->note);
    }

    public function test_it_allows_clearing_category_via_explicit_null(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create(['category_id' => $category->id]);

        $action = $this->app->make(UpdateFavoriteAction::class);

        $action->execute(new UpdateFavoriteData(
            favorite: $favorite,
            categoryId: null,
            categoryProvided: true,
            note: null,
            noteProvided: false,
        ));

        $this->assertNull($favorite->fresh()?->category_id);
    }

    public function test_it_leaves_fields_alone_when_not_provided(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        $favorite = Favorite::factory()->for($user)->create([
            'category_id' => $category->id,
            'note' => 'original',
        ]);

        $action = $this->app->make(UpdateFavoriteAction::class);

        $action->execute(new UpdateFavoriteData(
            favorite: $favorite,
            categoryId: null,
            categoryProvided: false,
            note: null,
            noteProvided: false,
        ));

        $fresh = $favorite->fresh();
        $this->assertSame($category->id, $fresh?->category_id);
        $this->assertSame('original', $fresh?->note);
    }
}
