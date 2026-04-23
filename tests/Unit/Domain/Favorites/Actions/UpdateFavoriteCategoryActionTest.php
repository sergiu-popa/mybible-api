<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\UpdateFavoriteCategoryAction;
use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteCategoryData;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateFavoriteCategoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_only_provided_fields(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create([
            'name' => 'Old',
            'color' => '#000000',
        ]);

        $action = $this->app->make(UpdateFavoriteCategoryAction::class);

        $action->execute(new UpdateFavoriteCategoryData(
            category: $category,
            name: 'New',
            nameProvided: true,
            color: null,
            colorProvided: false,
        ));

        $category->refresh();
        $this->assertSame('New', $category->name);
        $this->assertSame('#000000', $category->color);
    }

    public function test_it_clears_color_when_null_is_explicitly_provided(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create(['color' => '#FF0000']);

        $action = $this->app->make(UpdateFavoriteCategoryAction::class);

        $action->execute(new UpdateFavoriteCategoryData(
            category: $category,
            name: null,
            nameProvided: false,
            color: null,
            colorProvided: true,
        ));

        $category->refresh();
        $this->assertNull($category->color);
    }
}
