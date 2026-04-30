<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\References;

use Illuminate\Foundation\Http\FormRequest;

final class ValidateReferenceRequest extends FormRequest
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
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }

    public function reference(): string
    {
        /** @var string $reference */
        $reference = $this->validated('reference');

        return $reference;
    }
}
