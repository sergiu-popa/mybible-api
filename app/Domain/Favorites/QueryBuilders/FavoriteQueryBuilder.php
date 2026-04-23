<?php

declare(strict_types=1);

namespace App\Domain\Favorites\QueryBuilders;

use App\Domain\Favorites\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Favorite>
 */
final class FavoriteQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    /**
     * Filter by category id. `null` matches the virtual "Uncategorized"
     * bucket (rows with `category_id IS NULL`).
     */
    public function forCategory(?int $categoryId): self
    {
        if ($categoryId === null) {
            return $this->whereNull('category_id');
        }

        return $this->where('category_id', $categoryId);
    }

    /**
     * Match favorites whose canonical reference starts with the given book
     * abbreviation (e.g. `GEN` → `GEN.%`). The `(user_id, reference)` index
     * supports the prefix seek.
     */
    public function forBook(string $bookAbbreviation): self
    {
        return $this->where('reference', 'like', strtoupper($bookAbbreviation) . '.%');
    }
}
