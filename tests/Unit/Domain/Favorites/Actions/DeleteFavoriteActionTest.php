<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Favorites\Actions;

use App\Domain\Favorites\Actions\DeleteFavoriteAction;
use App\Domain\Favorites\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteFavoriteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_favorite(): void
    {
        $user = User::factory()->create();
        $favorite = Favorite::factory()->for($user)->create();

        $action = $this->app->make(DeleteFavoriteAction::class);
        $action->execute($favorite);

        $this->assertSoftDeleted('favorites', ['id' => $favorite->id]);
    }
}
