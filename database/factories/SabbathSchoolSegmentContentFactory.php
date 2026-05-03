<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SegmentContentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolSegmentContent>
 */
final class SabbathSchoolSegmentContentFactory extends Factory
{
    protected $model = SabbathSchoolSegmentContent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'segment_id' => SabbathSchoolSegment::factory(),
            'type' => SegmentContentType::Text->value,
            'title' => null,
            'position' => 0,
            'content' => '<p>' . fake()->paragraph() . '</p>',
        ];
    }

    public function text(): self
    {
        return $this->state(fn (): array => [
            'type' => SegmentContentType::Text->value,
        ]);
    }

    public function question(?string $prompt = null): self
    {
        return $this->state(fn () => [
            'type' => SegmentContentType::Question->value,
            'content' => $prompt ?? fake()->sentence(8) . '?',
        ]);
    }

    public function memoryVerse(): self
    {
        return $this->state(fn (): array => [
            'type' => SegmentContentType::MemoryVerse->value,
        ]);
    }

    public function forSegment(SabbathSchoolSegment $segment): self
    {
        return $this->state(fn (): array => [
            'segment_id' => $segment->id,
        ]);
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => [
            'position' => $position,
        ]);
    }
}
