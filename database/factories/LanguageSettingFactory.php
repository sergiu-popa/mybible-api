<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LanguageSetting>
 */
final class LanguageSettingFactory extends Factory
{
    protected $model = LanguageSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language' => fake()->randomElement(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            )),
            'default_bible_version_id' => null,
            'default_commentary_id' => null,
            'default_devotional_type_id' => null,
        ];
    }

    public function forLanguage(string $language): self
    {
        return $this->state(['language' => $language]);
    }
}
