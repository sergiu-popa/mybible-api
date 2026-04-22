<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BibleVersion>
 */
final class BibleVersionFactory extends Factory
{
    protected $model = BibleVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $abbr = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => fake()->unique()->sentence(2),
            'abbreviation' => $abbr,
            'language' => Language::En->value,
        ];
    }

    public function romanian(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Versiunea Dumitru Cornilescu',
            'abbreviation' => 'VDC',
            'language' => Language::Ro->value,
        ]);
    }
}
