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
            /** @var DevotionalFavorite|null $existing */
            $existing = DevotionalFavorite::withTrashed()
                ->where('user_id', $data->user->id)
                ->where('devotional_id', $data->devotionalId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                $existing->delete();

                return ToggleDevotionalFavoriteResult::deleted();
            }

            if ($existing !== null && $existing->trashed()) {
                $existing->restore();
                $existing->touch();
                $existing->load('devotional');

                return ToggleDevotionalFavoriteResult::created($existing);
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
