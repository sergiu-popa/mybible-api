<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolLesson>
 */
final class SabbathSchoolLessonFactory extends Factory
{
    protected $model = SabbathSchoolLesson::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::instance(fake()->dateTimeBetween('-2 years', '+2 months'))
            ->startOfWeek();

        return [
            'language' => Language::En->value,
            'age_group' => 'adult',
            'number' => fake()->numberBetween(1, 13),
            'title' => fake()->sentence(4),
            'date_from' => $start->toDateString(),
            'date_to' => $start->addDays(6)->toDateString(),
            'memory_verse' => null,
            'image_cdn_url' => null,
            'published_at' => now()->subDay(),
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'published_at' => null,
        ]);
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

    public function forTrimester(SabbathSchoolTrimester $trimester): self
    {
        return $this->state(fn (): array => [
            'trimester_id' => $trimester->id,
        ]);
    }

    public function numbered(int $number): self
    {
        return $this->state(fn (): array => [
            'number' => $number,
        ]);
    }

    public function publishedAt(CarbonImmutable $moment): self
    {
        return $this->state(fn (): array => [
            'published_at' => $moment,
        ]);
    }
}
