<?php

declare(strict_types=1);

namespace App\Http\Requests\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;

final class ListOlympiadThemesRequest extends FormRequest
{
    use PaginatesRead;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'language' => ['nullable', 'string'],
        ]);
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
}
