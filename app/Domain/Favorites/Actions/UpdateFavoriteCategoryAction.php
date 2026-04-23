<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteCategoryData;
use App\Domain\Favorites\Models\FavoriteCategory;

final class UpdateFavoriteCategoryAction
{
    public function execute(UpdateFavoriteCategoryData $data): FavoriteCategory
    {
        $category = $data->category;

        if ($data->nameProvided) {
            $category->name = (string) $data->name;
        }

        if ($data->colorProvided) {
            $category->color = $data->color;
        }

        $category->save();

        return $category;
    }
}
