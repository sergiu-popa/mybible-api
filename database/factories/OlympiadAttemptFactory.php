<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlympiadAttempt>
 */
final class OlympiadAttemptFactory extends Factory
{
    protected $model = OlympiadAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book' => 'GEN',
            'chapters_label' => '1-3',
            'language' => Language::Ro,
            'score' => 0,
            'total' => 5,
            'started_at' => CarbonImmutable::now()->subMinutes(10),
            'completed_at' => CarbonImmutable::now(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function inProgress(): self
    {
        return $this->state(fn (): array => ['completed_at' => null]);
    }
}
