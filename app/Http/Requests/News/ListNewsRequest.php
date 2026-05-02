<?php

declare(strict_types=1);

namespace App\Http\Requests\News;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNewsRequest extends FormRequest
{
    use PaginatesRead;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'language' => ['nullable', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
        ]);
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
