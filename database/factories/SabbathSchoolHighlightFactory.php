<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SabbathSchoolHighlight>
 */
final class SabbathSchoolHighlightFactory extends Factory
{
    protected $model = SabbathSchoolHighlight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'segment_content_id' => SabbathSchoolSegmentContent::factory()->question(),
            'sabbath_school_segment_id' => fn (array $attrs): int => $this->resolveSegmentId($attrs),
            'start_position' => 0,
            'end_position' => 16,
            'color' => '#FFEB3B',
            'passage' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function resolveSegmentId(array $attrs): int
    {
        $contentId = $attrs['segment_content_id'] ?? null;

        if ($contentId === null) {
            return SabbathSchoolSegment::factory()->create()->id;
        }

        $segmentId = SabbathSchoolSegmentContent::query()
            ->whereKey($contentId)
            ->value('segment_id');

        if (! is_int($segmentId)) {
            return SabbathSchoolSegment::factory()->create()->id;
        }

        return $segmentId;
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forSegment(SabbathSchoolSegment $segment): self
    {
        return $this->state(fn (): array => [
            'sabbath_school_segment_id' => $segment->id,
        ]);
    }

    public function forSegmentContent(SabbathSchoolSegmentContent $content): self
    {
        return $this->state(fn (): array => [
            'segment_content_id' => $content->id,
            'sabbath_school_segment_id' => $content->segment_id,
        ]);
    }

    public function withColor(string $color): self
    {
        return $this->state(fn (): array => [
            'color' => $color,
        ]);
    }
}
