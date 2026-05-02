<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Sync\Sync\SyncBuilder;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

final class ShowUserSyncAction
{
    /**
     * @param  iterable<SyncBuilder>  $builders
     */
    public function __construct(private readonly iterable $builders) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, DateTimeImmutable $since): array
    {
        $cap = (int) config('sync.per_type_cap', 5000);
        $syncedAt = Carbon::now()->toIso8601String();

        $payload = [];
        $truncatedMaxSeenAts = [];

        foreach ($this->builders as $builder) {
            $delta = $builder->fetch($userId, $since, $cap);

            $payload[$builder->key()] = [
                'upserted' => $delta->upserted,
                'deleted' => $delta->deleted,
            ];

            if ($delta->maxSeenAt !== null) {
                $truncatedMaxSeenAts[] = $delta->maxSeenAt;
            }
        }

        $nextSince = null;
        if ($truncatedMaxSeenAts !== []) {
            usort($truncatedMaxSeenAts, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
            $nextSince = $truncatedMaxSeenAts[0]->format(DATE_ATOM);
        }

        return array_merge(
            ['synced_at' => $syncedAt, 'next_since' => $nextSince],
            $payload,
        );
    }
}
