<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Domain\Sync\Sync\Builders\DevotionalFavoriteSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class DevotionalFavoriteSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return DevotionalFavorite::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(DevotionalFavoriteSyncBuilder::class);
    }
}
