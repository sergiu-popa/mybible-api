<?php

declare(strict_types=1);

namespace App\Http\Requests\Sync;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ShowUserSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
        ];
    }

    public function since(): DateTimeImmutable
    {
        $value = $this->validated('since');

        if (! is_string($value) || $value === '') {
            return new DateTimeImmutable('@0');
        }

        return new DateTimeImmutable($value);
    }
}
