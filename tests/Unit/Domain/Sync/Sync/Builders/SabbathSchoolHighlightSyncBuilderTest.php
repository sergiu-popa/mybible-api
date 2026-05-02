<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\Sync\Sync\Builders\SabbathSchoolHighlightSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SabbathSchoolHighlightSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return SabbathSchoolHighlight::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(SabbathSchoolHighlightSyncBuilder::class);
    }
}
