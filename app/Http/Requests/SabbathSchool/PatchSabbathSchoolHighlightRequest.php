<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use Illuminate\Foundation\Http\FormRequest;

final class PatchSabbathSchoolHighlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
        ];
    }

    public function color(): string
    {
        /** @var string $color */
        $color = $this->validated('color');

        return $color;
    }
}
