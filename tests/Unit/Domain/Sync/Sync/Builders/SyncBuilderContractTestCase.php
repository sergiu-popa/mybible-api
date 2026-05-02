<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Sync\Builders;

use App\Domain\Sync\Sync\SyncBuilder;
use App\Models\User;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared contract for the seven SyncBuilders.
 * Subclasses bind a concrete model + factory + builder.
 */
abstract class SyncBuilderContractTestCase extends TestCase
{
    use RefreshDatabase;

    /** @return Factory<Model> */
    abstract protected function factoryFor(User $user): Factory;

    abstract protected function builder(): SyncBuilder;

    public function test_full_sync_includes_every_users_row(): void
    {
        $user = User::factory()->create();
        $rows = $this->factoryFor($user)->count(3)->create();
        assert($rows instanceof Collection);

        $delta = $this->builder()->fetch(
            $user->id,
            new DateTimeImmutable('@0'),
            5000,
        );

        $this->assertCount(3, $delta->upserted);
        $this->assertSame([], $delta->deleted);
        $this->assertNull($delta->maxSeenAt);
        $this->assertCount(3, $rows);
    }

    public function test_delta_excludes_rows_older_than_since(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2025-01-01 10:00:00');
        $old = $this->factoryFor($user)->create();
        assert($old instanceof Model);

        Carbon::setTestNow('2025-06-01 10:00:00');
        $fresh = $this->factoryFor($user)->create();
        assert($fresh instanceof Model);

        Carbon::setTestNow(null);

        $delta = $this->builder()->fetch(
            $user->id,
            new DateTimeImmutable('2025-03-01T00:00:00Z'),
            5000,
        );

        $ids = array_map(static fn (array $r): mixed => $r['id'] ?? null, $delta->upserted);
        $this->assertContains($fresh->getKey(), $ids);
        $this->assertNotContains($old->getKey(), $ids);
    }

    public function test_trashed_rows_appear_in_deleted(): void
    {
        $user = User::factory()->create();
        $row = $this->factoryFor($user)->create();
        assert($row instanceof Model);

        $row->delete();

        $delta = $this->builder()->fetch(
            $user->id,
            new DateTimeImmutable('@0'),
            5000,
        );

        $this->assertContains($row->getKey(), $delta->deleted);
        $this->assertSame([], $delta->upserted);
    }

    public function test_cap_plus_one_trips_max_seen_at(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2025-01-01 10:00:00');
        $this->factoryFor($user)->create();
        Carbon::setTestNow('2025-01-01 11:00:00');
        $this->factoryFor($user)->create();
        Carbon::setTestNow('2025-01-01 12:00:00');
        $this->factoryFor($user)->create();
        Carbon::setTestNow(null);

        $delta = $this->builder()->fetch(
            $user->id,
            new DateTimeImmutable('@0'),
            2,
        );

        $this->assertCount(2, $delta->upserted);
        $this->assertNotNull($delta->maxSeenAt);
    }

    public function test_cross_user_rows_are_excluded(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $own = $this->factoryFor($owner)->create();
        assert($own instanceof Model);
        $this->factoryFor($other)->count(2)->create();

        $delta = $this->builder()->fetch(
            $owner->id,
            new DateTimeImmutable('@0'),
            5000,
        );

        $this->assertCount(1, $delta->upserted);
        $ids = array_map(static fn (array $r): mixed => $r['id'] ?? null, $delta->upserted);
        $this->assertSame([$own->getKey()], $ids);
    }
}
