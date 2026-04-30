<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Support\Facades\DB;

final class ReorderSegmentQuestionsAction
{
    /**
     * Persist a full ordering of question ids inside a single segment.
     * Questions outside the segment are ignored.
     *
     * @param  list<int>  $ids
     */
    public function execute(SabbathSchoolSegment $segment, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($segment, $ids): void {
            foreach ($ids as $position => $id) {
                SabbathSchoolQuestion::query()
                    ->whereKey($id)
                    ->where('sabbath_school_segment_id', $segment->id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
