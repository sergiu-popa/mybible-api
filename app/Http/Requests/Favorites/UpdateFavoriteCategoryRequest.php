<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteCategoryData;
use App\Domain\Favorites\Models\FavoriteCategory;
use Illuminate\Validation\Rule;

final class UpdateFavoriteCategoryRequest extends AuthorizedFavoriteCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $userId = $category instanceof FavoriteCategory ? $category->user_id : null;
        $categoryId = $category instanceof FavoriteCategory ? $category->id : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('favorite_categories', 'name')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($categoryId),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
        ];
    }

    public function toData(): UpdateFavoriteCategoryData
    {
        /** @var FavoriteCategory $category */
        $category = $this->route('category');

        $nameProvided = $this->has('name');
        $colorProvided = $this->has('color');

        $name = $nameProvided ? (string) $this->input('name') : null;
        $colorRaw = $this->input('color');
        $color = $colorProvided && is_string($colorRaw) && $colorRaw !== '' ? $colorRaw : null;

        return new UpdateFavoriteCategoryData(
            category: $category,
            name: $name,
            nameProvided: $nameProvided,
            color: $color,
            colorProvided: $colorProvided,
        );
    }
}
