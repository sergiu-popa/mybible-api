<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Collections\Models\CollectionTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionReference>
 */
final class CollectionReferenceFactory extends Factory
{
    protected $model = CollectionReference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_topic_id' => CollectionTopic::factory(),
            'reference' => 'GEN.1:1.VDC',
            'position' => fake()->numberBetween(0, 100),
        ];
    }

    public function valid(): self
    {
        return $this->state(fn (): array => ['reference' => 'GEN.1:1.VDC']);
    }

    public function malformed(): self
    {
        return $this->state(fn (): array => ['reference' => 'NOPE.bad-input']);
    }
}
