<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolSegment
 */
final class SabbathSchoolSegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day' => $this->day,
            'title' => $this->title,
            'content' => $this->content,
            'passages' => $this->passages ?? [],
            'questions' => SabbathSchoolQuestionResource::collection(
                $this->whenLoaded('questions'),
            ),
        ];
    }
}
