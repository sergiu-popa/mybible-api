<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\DataTransferObjects\ToggleDevotionalFavoriteData;
use App\Domain\Devotional\DataTransferObjects\ToggleDevotionalFavoriteResult;
use App\Domain\Devotional\Models\DevotionalFavorite;
use Illuminate\Support\Facades\DB;

final class ToggleDevotionalFavoriteAction
{
    public function execute(ToggleDevotionalFavoriteData $data): ToggleDevotionalFavoriteResult
    {
        return DB::transaction(function () use ($data): ToggleDevotionalFavoriteResult {
            $existing = DevotionalFavorite::query()
                ->forUser($data->user)
                ->where('devotional_id', $data->devotionalId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return ToggleDevotionalFavoriteResult::deleted();
            }

            $favorite = DevotionalFavorite::query()->create([
                'user_id' => $data->user->id,
                'devotional_id' => $data->devotionalId,
            ]);

            $favorite->load('devotional');

            return ToggleDevotionalFavoriteResult::created($favorite);
        });
    }
}
