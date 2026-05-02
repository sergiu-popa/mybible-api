<?php

declare(strict_types=1);

namespace App\Domain\Sync\Sync\Builders;

use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Domain\Sync\DataTransferObjects\SyncTypeDelta;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Http\Resources\Devotionals\DevotionalFavoriteResource;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

final class DevotionalFavoriteSyncBuilder implements SyncBuilder
{
    public function key(): string
    {
        return 'devotional_favorites';
    }

    public function fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta
    {
        $sinceString = $since->format('Y-m-d H:i:s');

        /** @var Collection<int, DevotionalFavorite> $rows */
        $rows = DevotionalFavorite::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($q) use ($sinceString): void {
                $q->where('updated_at', '>', $sinceString)
                    ->orWhere('deleted_at', '>', $sinceString);
            })
            ->with('devotional')
            ->orderBy('updated_at')
            ->limit($cap + 1)
            ->get();

        $truncated = $rows->count() > $cap;
        if ($truncated) {
            $rows = $rows->take($cap);
        }

        $upserted = [];
        $deleted = [];

        foreach ($rows as $row) {
            if ($row->trashed()) {
                $deleted[] = $row->id;
            } else {
                $upserted[] = DevotionalFavoriteResource::make($row)->resolve();
            }
        }

        $maxSeenAt = $truncated && $rows->isNotEmpty()
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows->last()->updated_at) ?: null
            : null;

        return new SyncTypeDelta($upserted, $deleted, $maxSeenAt);
    }
}
