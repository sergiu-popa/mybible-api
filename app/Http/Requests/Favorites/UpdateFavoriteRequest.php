<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\DataTransferObjects\UpdateFavoriteData;
use App\Domain\Favorites\Models\Favorite;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class UpdateFavoriteRequest extends AuthorizedFavoriteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('favorite_categories', 'id')
                    ->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // AC 7: reference is immutable after creation.
            if ($this->has('reference')) {
                $validator->errors()->add(
                    'reference',
                    'The reference cannot be changed after a favorite is created.',
                );
            }
        });
    }

    public function toData(): UpdateFavoriteData
    {
        /** @var Favorite $favorite */
        $favorite = $this->route('favorite');

        $categoryProvided = $this->has('category_id');
        $noteProvided = $this->has('note');

        $categoryRaw = $this->input('category_id');
        $categoryId = null;
        if ($categoryProvided && $categoryRaw !== null && $categoryRaw !== '') {
            $categoryId = (int) $categoryRaw;
        }

        $noteRaw = $this->input('note');
        $note = null;
        if ($noteProvided && is_string($noteRaw) && $noteRaw !== '') {
            $note = $noteRaw;
        }

        return new UpdateFavoriteData(
            favorite: $favorite,
            categoryId: $categoryId,
            categoryProvided: $categoryProvided,
            note: $note,
            noteProvided: $noteProvided,
        );
    }
}
