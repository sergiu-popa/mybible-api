<?php

declare(strict_types=1);

namespace App\Http\Requests\Devotionals;

use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowDevotionalRequest extends FormRequest
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
            'language' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(DevotionalType::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): FetchDevotionalData
    {
        return new FetchDevotionalData(
            language: $this->resolvedLanguage(),
            type: DevotionalType::from((string) $this->input('type')),
            date: $this->forDate(),
        );
    }

    private function resolvedLanguage(): Language
    {
        $value = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $value instanceof Language ? $value : Language::En;
    }

    private function forDate(): CarbonImmutable
    {
        $raw = $this->query('date');

        if (is_string($raw) && $raw !== '') {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $raw);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return CarbonImmutable::today();
    }
}
