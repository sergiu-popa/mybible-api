<?php

declare(strict_types=1);

namespace App\Http\Requests\Devotionals;

use App\Domain\Devotional\Actions\ResolveDevotionalTypeAction;
use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['required', 'string', 'max:64'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): FetchDevotionalData
    {
        $language = $this->resolvedLanguage();
        $type = app(ResolveDevotionalTypeAction::class)
            ->handle((string) $this->input('type'), $language);

        return new FetchDevotionalData(
            language: $language,
            typeId: $type->id,
            typeSlug: $type->slug,
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
            /** @var CarbonImmutable $parsed */
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $raw);

            return $parsed;
        }

        return CarbonImmutable::today();
    }
}
