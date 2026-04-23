<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Actions;

use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteData;
use App\Domain\Favorites\Models\Favorite;

final class UpdateFavoriteAction
{
    public function execute(UpdateFavoriteData $data): Favorite
    {
        $favorite = $data->favorite;

        if ($data->categoryProvided) {
            $favorite->category_id = $data->categoryId;
        }

        if ($data->noteProvided) {
            $favorite->note = $data->note;
        }

        $favorite->save();

        return $favorite;
    }
}
