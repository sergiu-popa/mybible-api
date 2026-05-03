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
        $hasContents = $this->relationLoaded('segmentContents')
            && $this->segmentContents->isNotEmpty();

        return [
            'id' => $this->id,
            'for_date' => $this->for_date?->toDateString(),
            'day' => $this->day,
            'title' => $this->title,
            // Legacy fallback: surface the longtext + passages only when
            // no typed content rows exist (handles not-yet-migrated
            // lessons during the rollout window).
            'content' => $hasContents ? null : $this->content,
            'passages' => $hasContents ? [] : ($this->passages ?? []),
            'contents' => SabbathSchoolSegmentContentResource::collection(
                $this->whenLoaded('segmentContents'),
            ),
        ];
    }
}
