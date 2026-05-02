<?php

declare(strict_types=1);

namespace App\Http\Requests\Collections;

use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;

final class ListCollectionTopicsRequest extends FormRequest
{
    use PaginatesRead;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'language' => ['nullable', 'string'],
        ]);
    }
}
