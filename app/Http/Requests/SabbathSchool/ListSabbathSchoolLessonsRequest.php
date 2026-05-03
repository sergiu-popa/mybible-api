<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSabbathSchoolLessonsRequest extends FormRequest
{
    use PaginatesRead;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'language' => ['nullable', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'trimester' => ['nullable', 'integer', 'exists:sabbath_school_trimesters,id'],
            'age_group' => ['nullable', 'string', 'max:50'],
        ]);
    }

    public function resolvedLanguage(): Language
    {
        $value = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $value instanceof Language ? $value : Language::En;
    }

    public function trimesterId(): ?int
    {
        $value = $this->validated('trimester');

        return is_numeric($value) ? (int) $value : null;
    }

    public function ageGroup(): ?string
    {
        $value = $this->validated('age_group');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
