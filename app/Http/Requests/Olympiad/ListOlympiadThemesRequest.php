<?php

declare(strict_types=1);

namespace App\Http\Requests\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Foundation\Http\FormRequest;

final class ListOlympiadThemesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

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
            'language' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function toFilter(): OlympiadThemeFilter
    {
        $language = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        if (! $language instanceof Language) {
            $language = Language::En;
        }

        return new OlympiadThemeFilter(
            language: $language,
            perPage: $this->perPage(),
        );
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
