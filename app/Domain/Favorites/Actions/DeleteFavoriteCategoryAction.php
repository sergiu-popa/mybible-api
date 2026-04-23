<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\Models\FavoriteCategory;

final class DeleteFavoriteCategoryAction
{
    /**
     * Cascade semantics (AC 4): favorites previously assigned to this category
     * are reparented to the virtual "Uncategorized" bucket. This is handled at
     * the DB layer by the migration's `ON DELETE SET NULL` foreign key
     * constraint, so the Action only needs to issue the category delete.
     */
    public function execute(FavoriteCategory $category): void
    {
        $category->delete();
    }
}
