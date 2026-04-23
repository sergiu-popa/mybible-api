<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\DataTransferObjects\CreateFavoriteCategoryData;
use App\Domain\Favorites\Models\FavoriteCategory;

final class CreateFavoriteCategoryAction
{
    public function execute(CreateFavoriteCategoryData $data): FavoriteCategory
    {
        return FavoriteCategory::query()->create([
            'user_id' => $data->user->id,
            'name' => $data->name,
            'color' => $data->color,
        ]);
    }
}
