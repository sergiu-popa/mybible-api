<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use Illuminate\Support\Facades\DB;

final class DeleteFavoriteCategoryAction
{
    /**
     * Cascade semantics (AC 4): favorites previously assigned to this category
     * are reparented to the virtual "Uncategorized" bucket by nulling
     * `favorites.category_id`. Wrapped in a transaction so an unexpected
     * failure leaves no half-cascaded state.
     *
     * Note: the migration's `onDelete('set null')` already handles this at
     * the DB level — the explicit update is kept to keep the Action as the
     * single source of truth for the cascade rule (and to allow future
     * observers to fire on the affected favorite rows, which a bare FK
     * cascade would skip).
     */
    public function execute(FavoriteCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            Favorite::query()
                ->where('category_id', $category->id)
                ->update(['category_id' => null]);

            $category->delete();
        });
    }
}
