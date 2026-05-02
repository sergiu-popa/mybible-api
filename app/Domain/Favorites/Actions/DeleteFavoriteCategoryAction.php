<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use Illuminate\Support\Facades\DB;

final class DeleteFavoriteCategoryAction
{
    public function execute(FavoriteCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            // ON DELETE SET NULL won't fire for soft deletes, so null out manually.
            // Include trashed favorites so soft-deleted rows still in the sync window
            // don't carry a dangling category_id to clients on next sync.
            Favorite::withTrashed()
                ->where('category_id', $category->id)
                ->update(['category_id' => null]);

            $category->delete();
        });
    }
}
