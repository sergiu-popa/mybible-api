<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait PaginatesRead
{
    public const int DEFAULT_PER_PAGE = 30;

    public const int MAX_PER_PAGE = 100;

    public function perPage(): int
    {
        $value = $this->query('per_page');

        if (! is_numeric($value)) {
            return static::DEFAULT_PER_PAGE;
        }

        return max(1, min(static::MAX_PER_PAGE, (int) $value));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function pageRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . static::MAX_PER_PAGE],
        ];
    }
}
