<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\CreateFavoriteCategoryAction;
use App\Domain\Favorites\DataTransferObjects\CreateFavoriteCategoryData;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateFavoriteCategoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_category_owned_by_the_user(): void
    {
        $user = User::factory()->create();

        $action = $this->app->make(CreateFavoriteCategoryAction::class);

        $category = $action->execute(new CreateFavoriteCategoryData(
            user: $user,
            name: 'Memorize',
            color: '#FF0000',
        ));

        $this->assertInstanceOf(FavoriteCategory::class, $category);
        $this->assertSame($user->id, $category->user_id);
        $this->assertSame('Memorize', $category->name);
        $this->assertSame('#FF0000', $category->color);
        $this->assertDatabaseHas('favorite_categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Memorize',
        ]);
    }

    public function test_it_allows_null_color(): void
    {
        $user = User::factory()->create();

        $action = $this->app->make(CreateFavoriteCategoryAction::class);

        $category = $action->execute(new CreateFavoriteCategoryData(
            user: $user,
            name: 'Hope',
            color: null,
        ));

        $this->assertNull($category->color);
    }
}
