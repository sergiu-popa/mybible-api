<?php

declare(strict_types=1);

namespace App\Http\Requests\EducationalResources;

use App\Domain\EducationalResources\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListResourcesByCategoryRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

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
            'type' => ['nullable', 'string', Rule::enum(ResourceType::class)],
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

    public function resourceType(): ?ResourceType
    {
        $value = $this->validated('type');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return ResourceType::tryFrom($value);
    }
}
