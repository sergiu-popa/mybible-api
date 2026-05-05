<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddReferencesBatchRequest extends FormRequest
{
    /** Subject types that downstream batch processors know how to enumerate. */
    public const ALLOWED_SUBJECT_TYPES = ['commentary_text', 'devotional', 'sabbath_school_segment_content'];

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
            'subject_type' => ['required', 'string', Rule::in(self::ALLOWED_SUBJECT_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'language' => [
                'required',
                'string',
                'size:2',
                Rule::in(array_map(fn (Language $l): string => $l->value, Language::cases())),
            ],
            'filters' => ['nullable', 'array'],
        ];
    }
}
