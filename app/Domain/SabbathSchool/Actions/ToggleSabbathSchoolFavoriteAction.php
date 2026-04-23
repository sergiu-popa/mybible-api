<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteData;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteResult;
use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use Illuminate\Support\Facades\DB;

final class ToggleSabbathSchoolFavoriteAction
{
    /**
     * Flip the favorite state on `(user, lesson, segment)`.
     *
     * When `segmentId` equals the whole-lesson sentinel (0), the row
     * represents a lesson-level favorite. Segment-level and lesson-level
     * favorites coexist independently on the unique index — toggling one
     * does not affect the other.
     */
    public function execute(ToggleSabbathSchoolFavoriteData $data): ToggleSabbathSchoolFavoriteResult
    {
        return DB::transaction(function () use ($data): ToggleSabbathSchoolFavoriteResult {
            $existing = SabbathSchoolFavorite::query()
                ->forUser($data->user)
                ->forLessonAndSegment($data->lessonId, $data->segmentId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return ToggleSabbathSchoolFavoriteResult::deleted();
            }

            $favorite = SabbathSchoolFavorite::query()->create([
                'user_id' => $data->user->id,
                'sabbath_school_lesson_id' => $data->lessonId,
                'sabbath_school_segment_id' => $data->segmentId,
            ]);

            return ToggleSabbathSchoolFavoriteResult::created($favorite);
        });
    }
}
