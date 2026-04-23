<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\Models\Favorite;

final class DeleteFavoriteAction
{
    public function execute(Favorite $favorite): void
    {
        $favorite->delete();
    }
}
