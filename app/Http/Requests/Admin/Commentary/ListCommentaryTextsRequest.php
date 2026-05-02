<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ListCommentaryTextsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 200;

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
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! BibleBookCatalog::hasBook(strtoupper($value))) {
                        $fail('The selected book is invalid.');
                    }
                },
            ],
            'chapter' => ['required', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function book(): string
    {
        /** @var string $book */
        $book = $this->validated('book');

        return strtoupper($book);
    }

    public function chapter(): int
    {
        return (int) $this->validated('chapter');
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }
}
