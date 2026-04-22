<?php

declare(strict_types=1);

namespace App\Http\Requests\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeRequest;
use App\Domain\Reference\ChapterRange;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowOlympiadThemeRequest extends FormRequest
{
    private ?ChapterRange $parsedRange = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $segment = (string) $this->route('chapters');

        // Defer to `fromSegment()` — malformed ranges raise
        // `InvalidReferenceException` which the exception handler maps
        // to 422 with the standard reference error envelope.
        $this->parsedRange = ChapterRange::fromSegment($segment);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book' => ['required', 'string', Rule::in(array_keys(BibleBookCatalog::BOOKS))],
            'language' => ['nullable', 'string'],
            'seed' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'book' => strtoupper((string) $this->route('book')),
        ]);
    }

    public function toDomainRequest(): OlympiadThemeRequest
    {
        $language = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        if (! $language instanceof Language) {
            $language = Language::En;
        }

        $seed = $this->input('seed');

        return new OlympiadThemeRequest(
            book: strtoupper((string) $this->route('book')),
            range: $this->parsedRange ?? ChapterRange::fromSegment((string) $this->route('chapters')),
            language: $language,
            seed: is_numeric($seed) ? (int) $seed : null,
        );
    }
}
