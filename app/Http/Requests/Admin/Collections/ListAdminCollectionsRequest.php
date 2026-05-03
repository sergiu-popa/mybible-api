<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Collections;

use Illuminate\Foundation\Http\FormRequest;

final class ListAdminCollectionsRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function pageNumber(): int
    {
        $value = $this->query('page');

        return is_numeric($value) ? max(1, (int) $value) : 1;
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        return is_numeric($value)
            ? max(1, min(self::MAX_PER_PAGE, (int) $value))
            : self::DEFAULT_PER_PAGE;
    }

    public function language(): ?string
    {
        $value = $this->query('language');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
