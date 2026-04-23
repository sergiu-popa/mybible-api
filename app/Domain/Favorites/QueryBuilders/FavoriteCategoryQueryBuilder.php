<?php

declare(strict_types=1);

namespace App\Domain\Favorites\QueryBuilders;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<FavoriteCategory>
 */
final class FavoriteCategoryQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function orderedByName(): self
    {
        return $this->orderBy('name');
    }
}
