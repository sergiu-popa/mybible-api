<?php

declare(strict_types=1);

namespace App\Http\Requests\Bible;

use App\Domain\Shared\Enums\Language;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBibleVersionsRequest extends FormRequest
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
            'language' => ['nullable', 'string', Rule::in(array_map(fn (Language $l) => $l->value, Language::cases()))],
        ]);
    }

    public function language(): ?Language
    {
        $value = $this->query('language');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Language::tryFrom($value);
    }
}
