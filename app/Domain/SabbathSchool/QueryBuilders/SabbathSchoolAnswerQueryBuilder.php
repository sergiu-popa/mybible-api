<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\QueryBuilders;

use App\Domain\SabbathSchool\Models\SabbathSchoolAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<SabbathSchoolAnswer>
 */
final class SabbathSchoolAnswerQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function forSegmentContent(int $segmentContentId): self
    {
        return $this->where('segment_content_id', $segmentContentId);
    }
}
