<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;

final class ListReadingPlansRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

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
            'language' => ['nullable', 'string', 'in:en,ro,hu'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function language(): Language
    {
        $value = $this->query('language');

        return Language::fromRequest(is_string($value) ? $value : null);
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
