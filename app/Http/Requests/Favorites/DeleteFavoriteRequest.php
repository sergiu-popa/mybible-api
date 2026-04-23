<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

final class DeleteFavoriteRequest extends AuthorizedFavoriteRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
