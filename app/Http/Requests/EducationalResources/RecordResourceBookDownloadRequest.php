<?php

declare(strict_types=1);

namespace App\Http\Requests\EducationalResources;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordResourceBookDownloadRequest extends FormRequest
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
            'device_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
        ];
    }
}
