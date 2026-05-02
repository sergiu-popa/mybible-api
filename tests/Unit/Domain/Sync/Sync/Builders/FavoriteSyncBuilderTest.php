<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Sync\Sync\Builders\FavoriteSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class FavoriteSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return Favorite::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(FavoriteSyncBuilder::class);
    }
}
