<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ListFavoritesRequest extends FormRequest
{
    /**
     * Sentinel distinguishing "category filter not supplied" from "filter supplied
     * and resolved to null (uncategorized bucket)".
     */
    public const NO_CATEGORY_FILTER = 'absent';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string'],
            'book' => ['sometimes', 'string', 'size:3', $this->bookAbbreviationRule()],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Returns the category filter resolution:
     *   - `self::NO_CATEGORY_FILTER` → filter not supplied, do not constrain
     *   - `null` → the "uncategorized" bucket (`category_id IS NULL`)
     *   - `int` → a specific category id
     */
    public function categoryFilter(): string|int|null
    {
        if (! $this->has('category')) {
            return self::NO_CATEGORY_FILTER;
        }

        $raw = $this->input('category');

        if ($raw === null || $raw === '' || (is_string($raw) && strtolower($raw) === 'uncategorized')) {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return self::NO_CATEGORY_FILTER;
    }

    public function bookFilter(): ?string
    {
        $book = $this->input('book');

        if (! is_string($book) || $book === '') {
            return null;
        }

        return strtoupper($book);
    }

    private function bookAbbreviationRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! is_string($value) || ! BibleBookCatalog::hasBook(strtoupper($value))) {
                    $fail('The :attribute must be a valid three-letter book abbreviation.');
                }
            }
        };
    }
}
