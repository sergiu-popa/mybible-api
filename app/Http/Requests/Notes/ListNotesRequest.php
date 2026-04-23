<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ListNotesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

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
            'book' => [
                'nullable',
                'string',
                'size:3',
                $this->bookExistsRule(),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function book(): ?string
    {
        $value = $this->query('book');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return strtoupper($value);
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }

    private function bookExistsRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! is_string($value) || $value === '') {
                    return;
                }

                if (! BibleBookCatalog::hasBook(strtoupper($value))) {
                    $fail('The selected :attribute is invalid.');
                }
            }
        };
    }
}
