<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use Illuminate\Foundation\Http\FormRequest;

final class ListSabbathSchoolFavoritesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 30;

    public const MAX_PER_PAGE = 100;

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
}
