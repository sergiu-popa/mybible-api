<?php

declare(strict_types=1);

namespace App\Http\Requests\Hymnal;

use Illuminate\Foundation\Http\FormRequest;

final class ShowHymnalSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
