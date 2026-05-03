<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Devotionals;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteDevotionalTypeRequest extends FormRequest
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
        return [];
    }
}
