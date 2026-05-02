<?php

declare(strict_types=1);

namespace App\Http\Requests\EducationalResources;

use App\Domain\Shared\Enums\Language;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListResourceCategoriesRequest extends FormRequest
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
        ]);
    }

    public function languageFilter(): ?Language
    {
        $value = $this->query('language');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Language::from($value);
    }
}
