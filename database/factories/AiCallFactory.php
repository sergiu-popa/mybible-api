<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\AI\Enums\AiCallStatus;
use App\Domain\AI\Models\AiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCall>
 */
final class AiCallFactory extends Factory
{
    protected $model = AiCall::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prompt_name' => 'add_references',
            'prompt_version' => '1.0.0',
            'model' => 'claude-sonnet-4-6',
            'input_tokens' => fake()->numberBetween(50, 500),
            'output_tokens' => fake()->numberBetween(50, 500),
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'latency_ms' => fake()->numberBetween(100, 2000),
            'status' => AiCallStatus::Ok,
            'error_message' => null,
            'subject_type' => null,
            'subject_id' => null,
            'triggered_by_user_id' => null,
        ];
    }

    public function failed(string $message = 'upstream error'): self
    {
        return $this->state([
            'status' => AiCallStatus::Error,
            'error_message' => $message,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);
    }
}
