<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCommentaryTextRequest extends FormRequest
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
        $commentary = $this->route('commentary');
        $text = $this->route('text');
        $commentaryId = $commentary instanceof Commentary ? $commentary->id : null;
        $textId = $text instanceof CommentaryText ? $text->id : null;
        $existingBook = $text instanceof CommentaryText ? $text->book : '';
        $existingChapter = $text instanceof CommentaryText ? $text->chapter : 0;

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
            'position' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('commentary_texts', 'position')
                    ->ignore($textId)
                    ->where(fn ($query) => $query
                        ->where('commentary_id', $commentaryId)
                        ->where('book', strtoupper((string) $this->input('book', $existingBook)))
                        ->where('chapter', (int) $this->input('chapter', $existingChapter))),
            ],
            'verse_from' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'verse_to' => ['sometimes', 'nullable', 'integer', 'min:1', 'gte:verse_from'],
            'verse_label' => ['sometimes', 'nullable', 'string', 'max:20'],
            'content' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        if (isset($validated['book']) && is_string($validated['book'])) {
            $validated['book'] = strtoupper($validated['book']);
        }

        return $validated;
    }
}
