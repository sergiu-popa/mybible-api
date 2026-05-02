<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\DataTransferObjects;

use App\Domain\Sync\DataTransferObjects\SyncTypeDelta;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SyncTypeDeltaTest extends TestCase
{
    public function test_it_exposes_constructor_arguments(): void
    {
        $maxSeenAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $delta = new SyncTypeDelta(
            upserted: [['id' => 1]],
            deleted: [9, 10],
            maxSeenAt: $maxSeenAt,
        );

        $this->assertSame([['id' => 1]], $delta->upserted);
        $this->assertSame([9, 10], $delta->deleted);
        $this->assertSame($maxSeenAt, $delta->maxSeenAt);
    }

    public function test_it_allows_null_max_seen_at(): void
    {
        $delta = new SyncTypeDelta([], [], null);

        $this->assertNull($delta->maxSeenAt);
    }

    public function test_it_is_readonly(): void
    {
        $reflection = new ReflectionClass(SyncTypeDelta::class);
        $this->assertTrue($reflection->isReadOnly(), 'SyncTypeDelta must be readonly.');

        $delta = new SyncTypeDelta([], [], null);

        $this->expectException(Error::class);
        // @phpstan-ignore-next-line — testing readonly enforcement at runtime
        $delta->upserted = [['id' => 99]];
    }
}
