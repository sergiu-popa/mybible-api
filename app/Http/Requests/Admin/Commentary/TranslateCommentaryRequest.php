<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TranslateCommentaryRequest extends FormRequest
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
            'target_language' => [
                'required',
                'string',
                'size:2',
                Rule::in(array_map(fn (Language $l): string => $l->value, Language::cases())),
            ],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }

    public function targetLanguage(): string
    {
        return (string) $this->validated('target_language');
    }

    public function overwrite(): bool
    {
        return $this->boolean('overwrite');
    }
}
