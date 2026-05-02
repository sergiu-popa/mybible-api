<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Sync\Sync\Builders\HymnalFavoriteSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class HymnalFavoriteSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return HymnalFavorite::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(HymnalFavoriteSyncBuilder::class);
    }
}
