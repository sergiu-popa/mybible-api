<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Migrations seed one row per ISO-2 language and `language` is unique,
     * so naive `create()` collides. Match-or-update by language instead so
     * `LanguageSetting::factory()->create([...])` is safe to call.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Model
    {
        $language = $attributes['language'] ?? null;
        if (is_string($language)) {
            $existing = LanguageSetting::query()->where('language', $language)->first();
            if ($existing instanceof LanguageSetting) {
                $existing->forceFill($attributes)->save();

                return $existing;
            }
        }

        return parent::newModel($attributes);
    }
}
