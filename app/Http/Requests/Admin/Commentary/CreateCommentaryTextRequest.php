<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Commentary\DataTransferObjects\CommentaryTextData;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Reference\Data\BibleBookCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCommentaryTextRequest extends FormRequest
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
        $commentaryId = $commentary instanceof Commentary ? $commentary->id : null;

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
            'position' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('commentary_texts', 'position')
                    ->where(fn ($query) => $query
                        ->where('commentary_id', $commentaryId)
                        ->where('book', strtoupper((string) $this->input('book', '')))
                        ->where('chapter', (int) $this->input('chapter', 0))),
            ],
            'verse_from' => ['nullable', 'integer', 'min:1'],
            'verse_to' => ['nullable', 'integer', 'min:1', 'gte:verse_from'],
            'verse_label' => ['nullable', 'string', 'max:20'],
            'content' => ['required', 'string'],
        ];
    }

    public function toData(): CommentaryTextData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CommentaryTextData::from($validated);
    }
}
