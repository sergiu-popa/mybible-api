<?php

declare(strict_types=1);

namespace App\Http\Requests\Verses;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class DailyVerseRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function forDate(): DateTimeImmutable
    {
        $raw = $this->input('date');

        if (is_string($raw) && $raw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return new DateTimeImmutable('today');
    }
}
