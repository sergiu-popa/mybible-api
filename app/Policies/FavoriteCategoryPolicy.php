<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;

final class FavoriteCategoryPolicy
{
    public function manage(User $user, FavoriteCategory $category): bool
    {
        return $category->user_id === $user->id;
    }
}
