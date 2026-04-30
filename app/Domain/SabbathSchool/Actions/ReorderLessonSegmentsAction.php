<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Support\Facades\DB;

final class ReorderLessonSegmentsAction
{
    /**
     * Persist a full ordering of segment ids inside a single lesson.
     * Segments not belonging to the lesson are ignored, so the action
     * cannot accidentally reshuffle siblings of another lesson if the
     * caller mixes ids.
     *
     * @param  list<int>  $ids
     */
    public function execute(SabbathSchoolLesson $lesson, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($lesson, $ids): void {
            foreach ($ids as $position => $id) {
                SabbathSchoolSegment::query()
                    ->whereKey($id)
                    ->where('sabbath_school_lesson_id', $lesson->id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
