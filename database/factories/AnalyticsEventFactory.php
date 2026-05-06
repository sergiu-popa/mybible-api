<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\EventType;
use App\Domain\Analytics\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsEvent>
 */
final class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            EventType::DevotionalViewed,
            EventType::SabbathSchoolLessonViewed,
            EventType::ResourceViewed,
        ]);

        return [
            'event_type' => $type->value,
            'subject_type' => $type->expectedSubjectType()?->value,
            'subject_id' => fake()->numberBetween(1, 1000),
            'user_id' => null,
            'device_id' => fake()->uuid(),
            'language' => 'ro',
            'source' => fake()->randomElement(['ios', 'android', 'web']),
            'app_version' => '1.0.0',
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }

    public function ofType(EventType $type): self
    {
        return $this->state(fn (): array => [
            'event_type' => $type->value,
            'subject_type' => $type->expectedSubjectType()?->value,
        ]);
    }

    public function forUser(int $userId): self
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }

    public function forDevice(string $deviceId): self
    {
        return $this->state(fn (): array => ['device_id' => $deviceId]);
    }
}
