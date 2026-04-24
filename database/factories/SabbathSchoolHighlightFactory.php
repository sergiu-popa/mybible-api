<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
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
            'sabbath_school_segment_id' => SabbathSchoolSegment::factory(),
            'passage' => 'GEN.1:1.VDC',
        ];
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
}
