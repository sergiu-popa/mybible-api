<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\Sync\Sync\Builders\SabbathSchoolFavoriteSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SabbathSchoolFavoriteSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return SabbathSchoolFavorite::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(SabbathSchoolFavoriteSyncBuilder::class);
    }
}
