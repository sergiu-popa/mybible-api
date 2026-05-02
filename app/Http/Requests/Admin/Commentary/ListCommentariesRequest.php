<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCommentariesRequest extends FormRequest
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
            'published' => ['nullable', 'boolean'],
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

    public function publishedFilter(): ?bool
    {
        if (! $this->has('published')) {
            return null;
        }

        return $this->boolean('published');
    }
}
