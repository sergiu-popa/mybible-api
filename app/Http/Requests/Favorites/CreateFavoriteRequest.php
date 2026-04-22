<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Favorites\DataTransferObjects\CreateFavoriteData;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Domain\Favorites\Rules\ParseableReference;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateFavoriteRequest extends FormRequest
{
    private ?ParseableReference $referenceRule = null;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $this->referenceRule = app(ParseableReference::class);

        $userId = $this->user()?->getAuthIdentifier();

        return [
            'reference' => ['required', 'string', 'max:255', $this->referenceRule],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('favorite_categories', 'id')
                    ->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): CreateFavoriteData
    {
        /** @var User $user */
        $user = $this->user();

        $reference = $this->referenceRule?->parsed();

        if ($reference === null) {
            // Defensive: rules() has run and validation passed, so the rule
            // memoized the parsed reference. This path should be unreachable.
            throw new \RuntimeException('Reference rule did not memoize a parsed reference.');
        }

        $categoryId = $this->validated('category_id');
        $category = is_int($categoryId)
            ? FavoriteCategory::query()->find($categoryId)
            : null;

        $note = $this->validated('note');

        return new CreateFavoriteData(
            user: $user,
            reference: $reference,
            category: $category instanceof FavoriteCategory ? $category : null,
            note: is_string($note) && $note !== '' ? $note : null,
        );
    }
}
