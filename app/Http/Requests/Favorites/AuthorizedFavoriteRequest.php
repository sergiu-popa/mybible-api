<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $favorite = $this->route('favorite');
        $user = $this->user();

        if (! $favorite instanceof Favorite || ! $user instanceof User) {
            return false;
        }

        return $user->can('manage', $favorite);
    }
}
