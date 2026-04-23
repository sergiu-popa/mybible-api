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

    public function forSegment(int $segmentId): self
    {
        return $this->where('sabbath_school_segment_id', $segmentId);
    }

    public function forPassage(string $passage): self
    {
        return $this->where('passage', $passage);
    }
}
