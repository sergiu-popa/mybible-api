<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\DataTransferObjects\CreateFavoriteCategoryData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateFavoriteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('favorite_categories', 'name')
                    ->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
        ];
    }

    public function toData(): CreateFavoriteCategoryData
    {
        /** @var User $user */
        $user = $this->user();

        /** @var string $name */
        $name = $this->validated('name');

        $color = $this->validated('color');

        return new CreateFavoriteCategoryData(
            user: $user,
            name: $name,
            color: is_string($color) ? $color : null,
        );
    }
}
