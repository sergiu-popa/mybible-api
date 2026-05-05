<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Domain\AI\DataTransferObjects\AddReferencesInput;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddReferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth + super-admin enforcement live in route middleware.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'html' => ['required', 'string', 'max:200000'],
            'language' => [
                'required',
                'string',
                'size:2',
                Rule::in(array_map(fn (Language $l): string => $l->value, Language::cases())),
            ],
            'bible_version_abbreviation' => ['nullable', 'string', 'exists:bible_versions,abbreviation'],
            'subject_type' => ['nullable', 'string', 'max:64'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): AddReferencesInput
    {
        $validated = $this->validated();
        $user = $this->user();

        return new AddReferencesInput(
            html: (string) $validated['html'],
            language: (string) $validated['language'],
            bibleVersionAbbreviation: isset($validated['bible_version_abbreviation'])
                ? (string) $validated['bible_version_abbreviation']
                : null,
            subjectType: isset($validated['subject_type']) ? (string) $validated['subject_type'] : null,
            subjectId: isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            triggeredByUserId: $user instanceof User ? (int) $user->id : null,
        );
    }
}
