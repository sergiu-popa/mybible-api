<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared shape for the two `ai-correct-batch` and `ai-add-references-batch`
 * commentary endpoints. Both accept the same optional `book` / `chapter`
 * filter to narrow the run.
 */
final class AICommentaryBatchRequest extends FormRequest
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
                'sometimes',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! BibleBookCatalog::hasBook(strtoupper($value))) {
                        $fail('The selected book is invalid.');
                    }
                },
            ],
            'chapter' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{book?: string, chapter?: int}
     */
    public function filters(): array
    {
        $filters = [];

        $book = $this->validated('book');
        if (is_string($book) && $book !== '') {
            $filters['book'] = strtoupper($book);
        }

        $chapter = $this->validated('chapter');
        if (is_int($chapter)) {
            $filters['chapter'] = $chapter;
        }

        return $filters;
    }
}
