<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Favorites\Models\Favorite;
use App\Models\User;

final class FavoritePolicy
{
    public function manage(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->id;
    }
}
