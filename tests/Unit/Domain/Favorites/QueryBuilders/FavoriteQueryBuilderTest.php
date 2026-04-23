<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\QueryBuilders;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FavoriteQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_returns_only_that_users_favorites(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceFav = Favorite::factory()->for($alice)->create();
        Favorite::factory()->for($bob)->create();

        $ids = Favorite::query()->forUser($alice)->pluck('id')->all();

        $this->assertSame([$aliceFav->id], $ids);
    }

    public function test_for_category_with_null_returns_the_uncategorized_bucket(): void
    {
        $user = User::factory()->create();
        $category = FavoriteCategory::factory()->for($user)->create();
        Favorite::factory()->for($user)->create(['category_id' => $category->id]);
        $uncategorized = Favorite::factory()->for($user)->create(['category_id' => null]);

        $ids = Favorite::query()->forUser($user)->forCategory(null)->pluck('id')->all();

        $this->assertSame([$uncategorized->id], $ids);
    }

    public function test_for_category_with_id_matches_that_category_only(): void
    {
        $user = User::factory()->create();
        $a = FavoriteCategory::factory()->for($user)->create();
        $b = FavoriteCategory::factory()->for($user)->create();
        $inA = Favorite::factory()->for($user)->create(['category_id' => $a->id]);
        Favorite::factory()->for($user)->create(['category_id' => $b->id]);

        $ids = Favorite::query()->forUser($user)->forCategory($a->id)->pluck('id')->all();

        $this->assertSame([$inA->id], $ids);
    }

    public function test_for_book_filters_via_canonical_prefix(): void
    {
        $user = User::factory()->create();
        $gen = Favorite::factory()->for($user)->create(['reference' => 'GEN.1:1.VDC']);
        Favorite::factory()->for($user)->create(['reference' => 'JHN.3:16.VDC']);

        $ids = Favorite::query()->forUser($user)->forBook('GEN')->pluck('id')->all();

        $this->assertSame([$gen->id], $ids);
    }

    public function test_for_book_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $gen = Favorite::factory()->for($user)->create(['reference' => 'GEN.1:1.VDC']);

        $ids = Favorite::query()->forUser($user)->forBook('gen')->pluck('id')->all();

        $this->assertSame([$gen->id], $ids);
    }
}
