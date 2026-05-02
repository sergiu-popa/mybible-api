<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowAppBootstrapRequest extends FormRequest
{
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
        ];
    }

    public function resolvedLanguage(): Language
    {
        $value = $this->query('language');

        if (is_string($value) && $value !== '') {
            return Language::from($value);
        }

        $attribute = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $attribute instanceof Language ? $attribute : Language::En;
    }
}
