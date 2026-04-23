<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\QueryBuilders;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FavoriteCategoryQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_returns_only_that_users_categories(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceCat = FavoriteCategory::factory()->for($alice)->create();
        FavoriteCategory::factory()->for($bob)->create();

        $ids = FavoriteCategory::query()->forUser($alice)->pluck('id')->all();

        $this->assertSame([$aliceCat->id], $ids);
    }

    public function test_ordered_by_name_sorts_ascending(): void
    {
        $user = User::factory()->create();
        FavoriteCategory::factory()->for($user)->create(['name' => 'Zeal']);
        FavoriteCategory::factory()->for($user)->create(['name' => 'Amen']);
        FavoriteCategory::factory()->for($user)->create(['name' => 'Memorize']);

        $names = FavoriteCategory::query()
            ->forUser($user)
            ->orderedByName()
            ->pluck('name')
            ->all();

        $this->assertSame(['Amen', 'Memorize', 'Zeal'], $names);
    }
}
