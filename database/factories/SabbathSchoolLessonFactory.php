<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
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
        $weekStart = CarbonImmutable::instance(fake()->dateTimeBetween('-2 years', '+2 months'))
            ->startOfWeek();

        return [
            'language' => Language::En->value,
            'title' => fake()->sentence(4),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->addDays(6)->toDateString(),
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

    public function publishedAt(CarbonImmutable $moment): self
    {
        return $this->state(fn (): array => [
            'published_at' => $moment,
        ]);
    }
}
