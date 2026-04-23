<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\Models\FavoriteCategory;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedFavoriteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');
        $user = $this->user();

        if (! $category instanceof FavoriteCategory || ! $user instanceof User) {
            return false;
        }

        return $user->can('manage', $category);
    }
}
