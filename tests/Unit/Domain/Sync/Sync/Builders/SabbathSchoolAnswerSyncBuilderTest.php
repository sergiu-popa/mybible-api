<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Domain\Sync\Sync\Builders\SabbathSchoolAnswerSyncBuilder;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SabbathSchoolAnswerSyncBuilderTest extends SyncBuilderContractTestCase
{
    protected function factoryFor(User $user): Factory
    {
        return SabbathSchoolAnswer::factory()->for($user);
    }

    protected function builder(): SyncBuilder
    {
        return $this->app->make(SabbathSchoolAnswerSyncBuilder::class);
    }
}
