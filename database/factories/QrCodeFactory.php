<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCode>
 */
final class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = sprintf(
            '%s.%d:%d.VDC',
            fake()->randomElement(['GEN', 'EXO', 'PSA', 'JHN', 'ROM']),
            fake()->numberBetween(1, 50),
            fake()->numberBetween(1, 30),
        );

        return [
            'reference' => $reference,
            'url' => 'https://web.mybible.local/' . strtolower(str_replace(['.', ':'], '-', $reference)),
            'image_path' => 'qr-' . fake()->unique()->numberBetween(1, 1_000_000) . '.png',
        ];
    }

    public function forReference(string $reference): self
    {
        return $this->state(fn (): array => ['reference' => $reference]);
    }

    public function withoutImage(): self
    {
        return $this->state(fn (): array => ['image_path' => null]);
    }
}
