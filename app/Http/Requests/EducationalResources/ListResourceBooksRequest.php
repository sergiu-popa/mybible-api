<?php

declare(strict_types=1);

namespace App\Http\Requests\EducationalResources;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListResourceBooksRequest extends FormRequest
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
            'language' => ['nullable', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function languageFilter(): ?Language
    {
        $value = $this->query('language');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Language::from($value);
    }

    public function pageNumber(): int
    {
        $value = $this->query('page');

        return is_numeric($value) ? max(1, (int) $value) : 1;
    }
}
