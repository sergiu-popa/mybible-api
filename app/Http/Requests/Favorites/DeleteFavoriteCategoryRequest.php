<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

final class DeleteFavoriteCategoryRequest extends AuthorizedFavoriteCategoryRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
