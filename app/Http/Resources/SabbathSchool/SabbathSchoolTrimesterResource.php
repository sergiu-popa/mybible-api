<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolTrimester
 */
final class SabbathSchoolTrimesterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'language' => $this->language,
            'age_group' => $this->age_group,
            'title' => $this->title,
            'number' => $this->number,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            'image_cdn_url' => $this->image_cdn_url,
            'lessons' => SabbathSchoolLessonSummaryResource::collection(
                $this->whenLoaded('lessons'),
            ),
        ];
    }
}
