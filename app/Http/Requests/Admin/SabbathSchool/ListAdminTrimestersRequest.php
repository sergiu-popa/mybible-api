<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SabbathSchool;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListAdminTrimestersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
        ];
    }

    public function language(): ?Language
    {
        $value = $this->validated('language');

        return is_string($value) ? Language::tryFrom($value) : null;
    }
}
