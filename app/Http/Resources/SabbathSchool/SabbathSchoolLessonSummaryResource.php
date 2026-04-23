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
            'title' => $this->title,
            'language' => $this->language,
            'week_start' => $this->week_start->toDateString(),
            'week_end' => $this->week_end->toDateString(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
