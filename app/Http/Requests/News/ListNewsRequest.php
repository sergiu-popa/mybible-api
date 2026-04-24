<?php

declare(strict_types=1);

namespace App\Http\Requests\News;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNewsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

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
            'language' => ['nullable', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
            'page' => ['nullable', 'integer', 'min:1'],
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

    /**
     * Resolve the language filter, falling back to the ResolveRequestLanguage
     * middleware attribute when the client omitted `?language=…`.
     */
    public function resolvedLanguage(): Language
    {
        $value = $this->query('language');

        if (is_string($value) && $value !== '') {
            // Validated by rules() — `from()` is safe here.
            return Language::from($value);
        }

        $attribute = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $attribute instanceof Language ? $attribute : Language::En;
    }
}
