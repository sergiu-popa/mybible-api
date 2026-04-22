<?php

declare(strict_types=1);

namespace App\Http\Requests\Hymnal;

use Illuminate\Foundation\Http\FormRequest;

final class ListHymnalBookSongsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }

    public function search(): ?string
    {
        $value = $this->query('search');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
