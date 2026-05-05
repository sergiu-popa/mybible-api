<?php

declare(strict_types=1);

namespace App\Http\Requests\Commentary;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Anonymous-friendly error report submission. `description` is the only
 * required field; `verse` is denormalised to support the public
 * reader's "I'm reading verse N" prefill.
 */
final class SubmitCommentaryErrorReportRequest extends FormRequest
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
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'verse' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
