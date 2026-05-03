<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolHighlight
 */
final class SabbathSchoolHighlightResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'segment_id' => $this->sabbath_school_segment_id,
            'segment_content_id' => $this->segment_content_id,
            'start_position' => $this->start_position,
            'end_position' => $this->end_position,
            'color' => $this->color,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
