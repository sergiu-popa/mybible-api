<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Olympiad;

use App\Domain\Reference\ChapterRange;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Shared\Enums\Language;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Validation\Rule;

final class ReorderOlympiadQuestionsRequest extends ReorderRequest
{
    private ?ChapterRange $parsedRange = null;

    protected function prepareForValidation(): void
    {
        $segment = (string) $this->route('chapters');

        // `fromSegment()` raises `InvalidReferenceException` on malformed
        // ranges; the exception handler maps it to 422 with the standard
        // reference error envelope, matching the public theme endpoint.
        $this->parsedRange = ChapterRange::fromSegment($segment);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'book' => ['required', 'string', Rule::in(array_keys(BibleBookCatalog::BOOKS))],
            'language' => ['required', 'string', Rule::in(array_column(Language::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'book' => strtoupper((string) $this->route('book')),
            'language' => strtolower((string) $this->route('language')),
        ]);
    }

    public function book(): string
    {
        return strtoupper((string) $this->route('book'));
    }

    public function range(): ChapterRange
    {
        return $this->parsedRange ?? ChapterRange::fromSegment((string) $this->route('chapters'));
    }

    public function language(): Language
    {
        return Language::from(strtolower((string) $this->route('language')));
    }
}
