<?php

declare(strict_types=1);

namespace App\Http\Requests\Devotionals;

use App\Domain\Devotional\DataTransferObjects\ListDevotionalArchiveData;
use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListDevotionalArchiveRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 30;

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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function toData(): ListDevotionalArchiveData
    {
        return new ListDevotionalArchiveData(
            language: $this->resolvedLanguage(),
            type: DevotionalType::from((string) $this->input('type')),
            from: $this->optionalDate('from'),
            to: $this->optionalDate('to'),
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

    private function resolvedLanguage(): Language
    {
        $value = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $value instanceof Language ? $value : Language::En;
    }

    private function optionalDate(string $key): ?CarbonImmutable
    {
        $raw = $this->query($key);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $raw);

        return $parsed instanceof CarbonImmutable ? $parsed : null;
    }
}
