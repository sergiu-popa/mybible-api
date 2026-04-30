<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportJob>
 */
final class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'bible.catalog',
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => null,
            'error' => null,
            'user_id' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(int $progress = 50): self
    {
        return $this->state(fn (): array => [
            'status' => ImportJobStatus::Running,
            'progress' => $progress,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): self
    {
        return $this->state(fn (): array => [
            'status' => ImportJobStatus::Succeeded,
            'progress' => 100,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'something broke'): self
    {
        return $this->state(fn (): array => [
            'status' => ImportJobStatus::Failed,
            'error' => $error,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
