<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderSegmentContentsAction
{
    /**
     * Persist a full ordering of segment-content ids inside a single
     * segment. Every id in `$ids` must belong to `$segment`; a mismatch
     * raises `ValidationException` so admin clients with stale ids see
     * a 422 instead of a silent partial reorder.
     *
     * @param  list<int>  $ids
     */
    public function execute(SabbathSchoolSegment $segment, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $matching = SabbathSchoolSegmentContent::query()
            ->where('segment_id', $segment->id)
            ->whereIn('id', $ids)
            ->count();

        if ($matching !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more ids do not belong to the target segment.'],
            ]);
        }

        DB::transaction(function () use ($segment, $ids): void {
            foreach ($ids as $position => $id) {
                SabbathSchoolSegmentContent::query()
                    ->whereKey($id)
                    ->where('segment_id', $segment->id)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
