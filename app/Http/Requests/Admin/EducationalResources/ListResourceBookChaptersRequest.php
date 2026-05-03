<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use Illuminate\Foundation\Http\FormRequest;

final class ListResourceBookChaptersRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
