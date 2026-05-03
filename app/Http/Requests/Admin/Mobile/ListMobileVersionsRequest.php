<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mobile;

use Illuminate\Foundation\Http\FormRequest;

final class ListMobileVersionsRequest extends FormRequest
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
            'platform' => ['nullable', 'string', 'in:ios,android'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function platform(): ?string
    {
        $value = $this->query('platform');

        return is_string($value) && $value !== '' ? $value : null;
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
}
