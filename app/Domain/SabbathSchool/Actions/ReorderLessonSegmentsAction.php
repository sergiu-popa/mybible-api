<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderLessonSegmentsAction
{
    /**
     * Persist a full ordering of segment ids inside a single lesson.
     * Every id in `$ids` must belong to `$lesson`; a mismatch raises
     * `ValidationException` so admin clients with stale ids see a 422
     * instead of a silent partial reorder.
     *
     * @param  list<int>  $ids
     */
    public function execute(SabbathSchoolLesson $lesson, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = SabbathSchoolSegment::query()
            ->where('sabbath_school_lesson_id', $lesson->id)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to the target lesson.'],
            ]);
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
