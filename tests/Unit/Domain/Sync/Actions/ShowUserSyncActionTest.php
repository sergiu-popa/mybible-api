<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Sync\Actions\ShowUserSyncAction;
use App\Domain\Sync\DataTransferObjects\SyncTypeDelta;
use App\Domain\Sync\Sync\SyncBuilder;
use DateTimeImmutable;
use Tests\TestCase;

final class ShowUserSyncActionTest extends TestCase
{
    public function test_it_aggregates_each_builder_under_its_key(): void
    {
        $builderA = new SpySyncBuilder('favorites', new SyncTypeDelta([['id' => 1]], [], null));
        $builderB = new SpySyncBuilder('notes', new SyncTypeDelta([['id' => 2]], [9], null));

        $action = new ShowUserSyncAction([$builderA, $builderB]);

        $payload = $action->execute(42, new DateTimeImmutable('@0'));

        $this->assertSame([['id' => 1]], $payload['favorites']['upserted']);
        $this->assertSame([], $payload['favorites']['deleted']);
        $this->assertSame([['id' => 2]], $payload['notes']['upserted']);
        $this->assertSame([9], $payload['notes']['deleted']);
        $this->assertNull($payload['next_since']);
        $this->assertNotEmpty($payload['synced_at']);
    }

    public function test_next_since_is_min_of_truncated_max_seen_ats(): void
    {
        $earlier = new DateTimeImmutable('2025-06-01T00:00:00Z');
        $later = new DateTimeImmutable('2025-12-01T00:00:00Z');

        $builderA = new SpySyncBuilder('favorites', new SyncTypeDelta([], [], $later));
        $builderB = new SpySyncBuilder('notes', new SyncTypeDelta([], [], $earlier));
        $builderC = new SpySyncBuilder('hymnal_favorites', new SyncTypeDelta([], [], null));

        $action = new ShowUserSyncAction([$builderA, $builderB, $builderC]);

        $payload = $action->execute(1, new DateTimeImmutable('@0'));

        $this->assertSame($earlier->format(DATE_ATOM), $payload['next_since']);
    }

    public function test_next_since_is_null_when_no_builder_truncates(): void
    {
        $builder = new SpySyncBuilder('favorites', new SyncTypeDelta([], [], null));

        $action = new ShowUserSyncAction([$builder]);

        $payload = $action->execute(1, new DateTimeImmutable('@0'));

        $this->assertNull($payload['next_since']);
    }

    public function test_it_passes_user_id_and_since_to_each_builder(): void
    {
        $since = new DateTimeImmutable('2025-03-01T00:00:00Z');
        $userId = 99;

        $builder = new SpySyncBuilder('favorites', new SyncTypeDelta([], [], null));

        $action = new ShowUserSyncAction([$builder]);
        $action->execute($userId, $since);

        $this->assertSame(1, $builder->callCount);
        $this->assertSame($userId, $builder->lastUserId);
        $this->assertSame($since, $builder->lastSince);
    }
}

final class SpySyncBuilder implements SyncBuilder
{
    public int $callCount = 0;

    public ?int $lastUserId = null;

    public ?DateTimeImmutable $lastSince = null;

    public function __construct(
        private readonly string $key,
        private readonly SyncTypeDelta $delta,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta
    {
        $this->callCount++;
        $this->lastUserId = $userId;
        $this->lastSince = $since;

        return $this->delta;
    }
}
