<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\Actions;

use App\Domain\Hymnal\DataTransferObjects\ToggleHymnalFavoriteData;
use App\Domain\Hymnal\DataTransferObjects\ToggleHymnalFavoriteResult;
use App\Domain\Hymnal\Models\HymnalFavorite;
use Illuminate\Support\Facades\DB;

final class ToggleHymnalFavoriteAction
{
    public function execute(ToggleHymnalFavoriteData $data): ToggleHymnalFavoriteResult
    {
        return DB::transaction(function () use ($data): ToggleHymnalFavoriteResult {
            /** @var HymnalFavorite|null $existing */
            $existing = HymnalFavorite::withTrashed()
                ->where('user_id', $data->user->id)
                ->where('hymnal_song_id', $data->song->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                $existing->delete();

                return new ToggleHymnalFavoriteResult($existing, false);
            }

            if ($existing !== null && $existing->trashed()) {
                $existing->restore();
                $existing->touch();

                return new ToggleHymnalFavoriteResult($existing, true);
            }

            $favorite = HymnalFavorite::query()->create([
                'user_id' => $data->user->id,
                'hymnal_song_id' => $data->song->id,
            ]);

            return new ToggleHymnalFavoriteResult($favorite, true);
        });
    }
}
