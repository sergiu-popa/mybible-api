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
            $existing = HymnalFavorite::query()
                ->forUser($data->user)
                ->forSong($data->song)
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return new ToggleHymnalFavoriteResult($existing, false);
            }

            $favorite = HymnalFavorite::query()->create([
                'user_id' => $data->user->id,
                'hymnal_song_id' => $data->song->id,
            ]);

            return new ToggleHymnalFavoriteResult($favorite, true);
        });
    }
}
