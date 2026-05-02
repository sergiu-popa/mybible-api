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
            'since' => ['nullable', 'string'],
        ];
    }

    public function since(): DateTimeImmutable
    {
        $value = $this->query('since');

        if (! is_string($value) || $value === '') {
            return new DateTimeImmutable('@0');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return new DateTimeImmutable('@0');
        }
    }
}
