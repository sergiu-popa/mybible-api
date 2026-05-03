<?php

declare(strict_types=1);

namespace App\Http\Requests\Collections;

use Illuminate\Foundation\Http\FormRequest;

final class ShowCollectionRequest extends FormRequest
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
            'language' => ['nullable', 'string'],
        ];
    }
}
