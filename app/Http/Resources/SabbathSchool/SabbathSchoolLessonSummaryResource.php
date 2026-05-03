<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolLesson
 */
final class SabbathSchoolLessonSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trimester_id' => $this->trimester_id,
            'language' => $this->language,
            'age_group' => $this->age_group,
            'number' => $this->number,
            'title' => $this->title,
            'image_cdn_url' => $this->image_cdn_url,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            // Legacy aliases retained for the rollout window.
            // TODO MBA-032: drop after mobile cutover.
            'week_start' => $this->date_from->toDateString(),
            'week_end' => $this->date_to->toDateString(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
