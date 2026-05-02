<?php

declare(strict_types=1);

namespace App\Http\Requests\Commentary;

use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ListCommentaryChapterTextsRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            'book' => $this->route('book'),
            'chapter' => $this->route('chapter'),
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
}
