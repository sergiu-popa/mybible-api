<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\Shared\Enums\Language;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListAdminLessonsRequest extends FormRequest
{
    use PaginatesRead;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'published' => ['nullable', 'boolean'],
        ]);
    }

    public function language(): ?Language
    {
        $value = $this->validated('language');

        return is_string($value) ? Language::tryFrom($value) : null;
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

    public function published(): ?bool
    {
        if (! $this->has('published')) {
            return null;
        }

        return (bool) $this->validated('published');
    }
}
