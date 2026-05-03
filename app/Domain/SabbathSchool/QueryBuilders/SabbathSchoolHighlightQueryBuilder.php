<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\QueryBuilders;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<SabbathSchoolHighlight>
 */
final class SabbathSchoolHighlightQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    /**
     * List all highlights anchored to any content block belonging to
     * the given segment. Joined for the public list endpoint.
     */
    public function forSegment(int $segmentId): self
    {
        return $this
            ->join(
                'sabbath_school_segment_contents as ssc',
                'ssc.id',
                '=',
                'sabbath_school_highlights.segment_content_id',
            )
            ->where('ssc.segment_id', $segmentId)
            ->select('sabbath_school_highlights.*');
    }

    public function forSegmentContent(int $segmentContentId): self
    {
        return $this->where('segment_content_id', $segmentContentId);
    }

    public function forContentRange(int $segmentContentId, int $start, int $end): self
    {
        return $this
            ->where('segment_content_id', $segmentContentId)
            ->where('start_position', $start)
            ->where('end_position', $end);
    }

    /**
     * Filter out un-migrated rows so the public API never serves a
     * half-shaped highlight (legacy `passage` only) during the rollout
     * window.
     */
    public function migrated(): self
    {
        return $this->whereNotNull('segment_content_id');
    }
}
