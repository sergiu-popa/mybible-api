<?php

declare(strict_types=1);

namespace App\Http\Requests\Verses;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Domain\Shared\Enums\Language;
use App\Domain\Verses\DataTransferObjects\ResolveVersesData;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class ResolveVersesRequest extends FormRequest
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
            'reference' => ['nullable', 'string', 'max:200'],
            'book' => ['nullable', 'string', 'max:8'],
            'chapter' => ['nullable', 'integer', 'min:1'],
            'verses' => ['nullable', 'string', 'max:100', 'regex:/^[0-9,\-]+$/'],
            'version' => ['nullable', 'string', 'max:16'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasReference = $this->filled('reference');
            $hasSplit = $this->filled('book') || $this->filled('chapter') || $this->filled('verses');

            if ($hasReference && $hasSplit) {
                $validator->errors()->add(
                    'reference',
                    'Provide either "reference" or the split parameters (book, chapter, verses) — not both.',
                );

                return;
            }

            if (! $hasReference && ! $hasSplit) {
                $validator->errors()->add('reference', 'A "reference" or split parameters are required.');

                return;
            }

            if ($hasSplit && (! $this->filled('book') || ! $this->filled('chapter'))) {
                if (! $this->filled('book')) {
                    $validator->errors()->add('book', 'The "book" field is required when using split parameters.');
                }

                if (! $this->filled('chapter')) {
                    $validator->errors()->add('chapter', 'The "chapter" field is required when using split parameters.');
                }
            }
        });
    }

    public function toData(ReferenceParser $parser): ResolveVersesData
    {
        $version = $this->resolveVersion();
        $query = $this->canonicalReferenceString($version);

        /** @var array<int, Reference> $references */
        $references = $parser->parse($query);

        $normalized = [];

        foreach ($references as $reference) {
            $normalized[] = new Reference(
                book: $reference->book,
                chapter: $reference->chapter,
                verses: $reference->verses,
                version: $reference->version ?? $version,
            );
        }

        return new ResolveVersesData(
            references: $normalized,
            version: $version,
        );
    }

    private function canonicalReferenceString(string $version): string
    {
        $reference = $this->input('reference');

        if (is_string($reference) && $reference !== '') {
            return $this->stampVersion($reference, $version);
        }

        $book = (string) $this->input('book');
        $chapter = (int) $this->input('chapter');
        $verses = $this->input('verses');

        $passage = $verses === null || $verses === ''
            ? (string) $chapter
            : sprintf('%d:%s', $chapter, $verses);

        return sprintf('%s.%s.%s', strtoupper($book), $passage, $version);
    }

    /**
     * Force the requested/resolved version onto every sub-reference of the
     * canonical query. The reference parser accepts per-sub versions, but
     * our endpoint resolves one version for the whole request.
     */
    private function stampVersion(string $reference, string $version): string
    {
        $parts = explode('.', $reference);

        if (count($parts) === 3) {
            if ($parts[2] === '') {
                $parts[2] = $version;
            }

            return implode('.', $parts);
        }

        if (count($parts) === 2) {
            return sprintf('%s.%s', $reference, $version);
        }

        return $reference;
    }

    private function resolveVersion(): string
    {
        $explicit = $this->input('version');

        if (is_string($explicit) && $explicit !== '') {
            $normalized = strtoupper($explicit);

            if (! $this->versionExists($normalized)) {
                throw ValidationException::withMessages([
                    'version' => 'The selected version is invalid.',
                ]);
            }

            return $normalized;
        }

        $embedded = $this->versionFromReference();

        if ($embedded !== null && $this->versionExists($embedded)) {
            return $embedded;
        }

        if ($embedded !== null) {
            throw ValidationException::withMessages([
                'version' => 'The selected version is invalid.',
            ]);
        }

        // MBA-018 will add a `preferred_version` column on users. Read it
        // dynamically so this story does not depend on that migration.
        $preferred = $this->user()?->getAttribute('preferred_version');

        if (is_string($preferred) && $preferred !== '' && $this->versionExists($preferred)) {
            return strtoupper($preferred);
        }

        $language = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        if ($language instanceof Language) {
            /** @var array<string, string> $map */
            $map = config('bible.default_version_by_language', []);
            $candidate = $map[$language->value] ?? null;

            if (is_string($candidate) && $candidate !== '' && $this->versionExists($candidate)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'version' => 'Version is required.',
        ]);
    }

    private function versionFromReference(): ?string
    {
        $reference = $this->input('reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $parts = explode('.', $reference);

        if (count($parts) !== 3 || $parts[2] === '') {
            return null;
        }

        return strtoupper($parts[2]);
    }

    private function versionExists(string $abbreviation): bool
    {
        return BibleVersion::query()->where('abbreviation', $abbreviation)->exists();
    }
}
