<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Devotionals;

use Illuminate\Foundation\Http\FormRequest;

final class ListDevotionalTypesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

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
            'language' => ['nullable', 'string', 'size:2'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        return is_numeric($value)
            ? max(1, min(self::MAX_PER_PAGE, (int) $value))
            : self::DEFAULT_PER_PAGE;
    }
}
