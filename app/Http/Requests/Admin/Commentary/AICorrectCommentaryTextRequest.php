<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use Illuminate\Foundation\Http\FormRequest;

final class AICorrectCommentaryTextRequest extends FormRequest
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
        return [];
    }
}
