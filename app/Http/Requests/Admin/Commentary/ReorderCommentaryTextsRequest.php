<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderCommentaryTextsRequest extends FormRequest
{
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
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

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('ids');

        return $ids;
    }
}
