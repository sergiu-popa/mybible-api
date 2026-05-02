<?php

declare(strict_types=1);

namespace App\Domain\Sync\Sync\Builders;

use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use App\Domain\Sync\DataTransferObjects\SyncTypeDelta;
use App\Domain\Sync\Sync\SyncBuilder;
use App\Http\Resources\SabbathSchool\SabbathSchoolFavoriteResource;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

final class SabbathSchoolFavoriteSyncBuilder implements SyncBuilder
{
    public function key(): string
    {
        return 'sabbath_school_favorites';
    }

    public function fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta
    {
        $sinceString = $since->format('Y-m-d H:i:s');

        /** @var Collection<int, SabbathSchoolFavorite> $rows */
        $rows = SabbathSchoolFavorite::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($q) use ($sinceString): void {
                $q->where('updated_at', '>', $sinceString)
                    ->orWhere('deleted_at', '>', $sinceString);
            })
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
                $upserted[] = SabbathSchoolFavoriteResource::make($row)->resolve();
            }
        }

        $maxSeenAt = $truncated && $rows->isNotEmpty()
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows->last()->updated_at) ?: null
            : null;

        return new SyncTypeDelta($upserted, $deleted, $maxSeenAt);
    }
}
