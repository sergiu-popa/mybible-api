<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolTrimester>
 */
final class SabbathSchoolTrimesterFactory extends Factory
{
    protected $model = SabbathSchoolTrimester::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::instance(fake()->dateTimeBetween('-2 years', '+2 months'))
            ->startOfWeek();

        return [
            'year' => $start->format('Y'),
            'language' => Language::En->value,
            'age_group' => 'adult',
            'title' => fake()->sentence(3),
            'number' => fake()->numberBetween(1, 13),
            'date_from' => $start->toDateString(),
            'date_to' => $start->addDays(90)->toDateString(),
            'image_cdn_url' => null,
        ];
    }

    public function forLanguage(Language $language): self
    {
        return $this->state(fn (): array => [
            'language' => $language->value,
        ]);
    }

    public function forAgeGroup(string $ageGroup): self
    {
        return $this->state(fn (): array => [
            'age_group' => $ageGroup,
        ]);
    }

    public function withImage(): self
    {
        return $this->state(fn (): array => [
            'image_cdn_url' => 'https://cdn.example.com/trimester-' . fake()->uuid() . '.jpg',
        ]);
    }
}
